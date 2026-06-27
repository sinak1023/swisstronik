// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import {SSTORE2} from "./SSTORE2.sol";

/// @title  ArcInvoiceRegistry
/// @notice On-chain invoice authenticity + atomic USDC payment for Circle's Arc L1.
///
/// Model C (hybrid):
///   - A keccak256 hash of the original file is stored on-chain for instant, cheap
///     authenticity verification.
///   - The full file bytes (compressed client-side) are stored on-chain via SSTORE2
///     across one or more "chunk" data contracts, so the document itself is immutable
///     and retrievable even if the issuer's server disappears.
///   - Payment is optional. When enabled, the customer pays in native USDC (Arc's gas
///     token) and the contract atomically splits it: (100% - feeBps) to the issuer and
///     feeBps to the platform, in a single transaction. Funds never custody on a server.
///
/// On Arc, USDC IS the native value token, so payments use msg.value (no ERC-20 approve).
contract ArcInvoiceRegistry {
    using SSTORE2 for bytes;

    // ---------------------------------------------------------------------
    // Types
    // ---------------------------------------------------------------------

    struct Invoice {
        address issuer;          // who registered the invoice (receives payment)
        bytes32 fileHash;        // keccak256 of the ORIGINAL (pre-compression) file
        address[] chunks;        // SSTORE2 data-contract pointers, in order
        uint64  createdAt;       // block timestamp of registration
        uint128 amountDue;       // requested payment in USDC base units (6 decimals); 0 = pay disabled
        uint128 amountPaid;      // cumulative amount paid so far
        bool    paid;            // true once amountPaid >= amountDue (and amountDue > 0)
        bool    paymentEnabled;  // whether the pay button is active for this invoice
        string  fileName;        // e.g. "invoice-2026-04-001.pdf"
        string  mimeType;        // e.g. "application/pdf", "image/png"
        string  metadata;        // free-form issuer-supplied JSON (number, customer, currency...)
    }

    // ---------------------------------------------------------------------
    // Storage
    // ---------------------------------------------------------------------

    address public owner;        // platform owner (receives platform fee)
    address public feeRecipient; // wallet that collects the platform fee
    uint16  public feeBps;       // platform fee in basis points (e.g. 200 = 2.00%)
    uint16  public constant MAX_FEE_BPS = 500; // hard cap 5% so the owner can't rug users

    uint256 public invoiceCount;
    mapping(uint256 => Invoice) private _invoices;

    // ---------------------------------------------------------------------
    // Events
    // ---------------------------------------------------------------------

    event InvoiceRegistered(
        uint256 indexed id,
        address indexed issuer,
        bytes32 fileHash,
        uint256 chunkCount,
        uint128 amountDue,
        bool    paymentEnabled
    );
    event InvoicePaid(
        uint256 indexed id,
        address indexed payer,
        uint256 amountToIssuer,
        uint256 platformFee,
        bool    fullySettled
    );
    event FeeUpdated(uint16 oldBps, uint16 newBps);
    event FeeRecipientUpdated(address indexed oldRecipient, address indexed newRecipient);
    event OwnershipTransferred(address indexed oldOwner, address indexed newOwner);

    // ---------------------------------------------------------------------
    // Errors
    // ---------------------------------------------------------------------

    error NotOwner();
    error FeeTooHigh();
    error ZeroAddress();
    error NoChunks();
    error InvoiceNotFound();
    error PaymentNotEnabled();
    error WrongAmount();
    error AlreadyPaid();
    error TransferFailed();
    error ChunkOutOfBounds();

    // ---------------------------------------------------------------------
    // Constructor / admin
    // ---------------------------------------------------------------------

    constructor(address _feeRecipient, uint16 _feeBps) {
        if (_feeRecipient == address(0)) revert ZeroAddress();
        if (_feeBps > MAX_FEE_BPS) revert FeeTooHigh();
        owner = msg.sender;
        feeRecipient = _feeRecipient;
        feeBps = _feeBps;
        emit OwnershipTransferred(address(0), msg.sender);
    }

    modifier onlyOwner() {
        if (msg.sender != owner) revert NotOwner();
        _;
    }

    function setFeeBps(uint16 _feeBps) external onlyOwner {
        if (_feeBps > MAX_FEE_BPS) revert FeeTooHigh();
        emit FeeUpdated(feeBps, _feeBps);
        feeBps = _feeBps;
    }

    function setFeeRecipient(address _feeRecipient) external onlyOwner {
        if (_feeRecipient == address(0)) revert ZeroAddress();
        emit FeeRecipientUpdated(feeRecipient, _feeRecipient);
        feeRecipient = _feeRecipient;
    }

    function transferOwnership(address _newOwner) external onlyOwner {
        if (_newOwner == address(0)) revert ZeroAddress();
        emit OwnershipTransferred(owner, _newOwner);
        owner = _newOwner;
    }

    // ---------------------------------------------------------------------
    // Registration
    // ---------------------------------------------------------------------

    /// @notice Register a new invoice fully on-chain.
    /// @param fileHash        keccak256 of the original (pre-compression) file bytes.
    /// @param chunkData       Array of compressed file chunks (each <= ~24KB). Stored via SSTORE2.
    /// @param amountDue       USDC base units (6 decimals) the customer should pay; 0 disables payment.
    /// @param paymentEnabled  Whether the pay button should be active.
    /// @param fileName        Display name of the file.
    /// @param mimeType        MIME type, used by the verifier UI to render the file.
    /// @param metadata        Free-form JSON metadata (invoice no., customer, currency, ...).
    /// @return id             The new invoice id.
    function registerInvoice(
        bytes32 fileHash,
        bytes[] calldata chunkData,
        uint128 amountDue,
        bool paymentEnabled,
        string calldata fileName,
        string calldata mimeType,
        string calldata metadata
    ) external returns (uint256 id) {
        if (chunkData.length == 0) revert NoChunks();

        address[] memory pointers = new address[](chunkData.length);
        for (uint256 i = 0; i < chunkData.length; i++) {
            pointers[i] = SSTORE2.write(chunkData[i]);
        }

        id = ++invoiceCount;
        Invoice storage inv = _invoices[id];
        inv.issuer = msg.sender;
        inv.fileHash = fileHash;
        inv.chunks = pointers;
        inv.createdAt = uint64(block.timestamp);
        inv.amountDue = amountDue;
        inv.paymentEnabled = paymentEnabled && amountDue > 0;
        inv.fileName = fileName;
        inv.mimeType = mimeType;
        inv.metadata = metadata;

        emit InvoiceRegistered(
            id, msg.sender, fileHash, chunkData.length, amountDue, inv.paymentEnabled
        );
    }

    // ---------------------------------------------------------------------
    // Reading (free, off-chain via eth_call)
    // ---------------------------------------------------------------------

    /// @notice Returns invoice metadata (without the heavy file bytes).
    function getInvoiceMeta(uint256 id)
        external
        view
        returns (
            address issuer,
            bytes32 fileHash,
            uint256 chunkCount,
            uint64  createdAt,
            uint128 amountDue,
            uint128 amountPaid,
            bool    paid,
            bool    paymentEnabled,
            string memory fileName,
            string memory mimeType,
            string memory metadata
        )
    {
        Invoice storage inv = _invoices[id];
        if (inv.issuer == address(0)) revert InvoiceNotFound();
        return (
            inv.issuer,
            inv.fileHash,
            inv.chunks.length,
            inv.createdAt,
            inv.amountDue,
            inv.amountPaid,
            inv.paid,
            inv.paymentEnabled,
            inv.fileName,
            inv.mimeType,
            inv.metadata
        );
    }

    /// @notice Returns the list of SSTORE2 chunk pointers for an invoice.
    function getChunkPointers(uint256 id) external view returns (address[] memory) {
        Invoice storage inv = _invoices[id];
        if (inv.issuer == address(0)) revert InvoiceNotFound();
        return inv.chunks;
    }

    /// @notice Reads a single chunk's bytes. Called per-chunk by the verifier to
    ///         avoid hitting RPC response-size limits for large files.
    function readChunk(uint256 id, uint256 index) external view returns (bytes memory) {
        Invoice storage inv = _invoices[id];
        if (inv.issuer == address(0)) revert InvoiceNotFound();
        if (index >= inv.chunks.length) revert ChunkOutOfBounds();
        return SSTORE2.read(inv.chunks[index]);
    }

    /// @notice Convenience: reads and concatenates all chunks into the full compressed
    ///         payload. Only safe for small files; large files should use readChunk loop.
    function readAll(uint256 id) external view returns (bytes memory out) {
        Invoice storage inv = _invoices[id];
        if (inv.issuer == address(0)) revert InvoiceNotFound();
        for (uint256 i = 0; i < inv.chunks.length; i++) {
            out = bytes.concat(out, SSTORE2.read(inv.chunks[i]));
        }
    }

    /// @notice Off-chain helper: does `claimedFileHash` match what's stored on-chain?
    function verifyHash(uint256 id, bytes32 claimedFileHash) external view returns (bool) {
        Invoice storage inv = _invoices[id];
        if (inv.issuer == address(0)) revert InvoiceNotFound();
        return inv.fileHash == claimedFileHash;
    }

    // ---------------------------------------------------------------------
    // Payment (atomic split)
    // ---------------------------------------------------------------------

    /// @notice Pay an invoice in native USDC. The contract atomically forwards
    ///         (amount - fee) to the issuer and `fee` to the platform recipient.
    ///         No funds are ever held by this contract.
    /// @param id The invoice to pay.
    function payInvoice(uint256 id) external payable {
        Invoice storage inv = _invoices[id];
        if (inv.issuer == address(0)) revert InvoiceNotFound();
        if (!inv.paymentEnabled) revert PaymentNotEnabled();
        if (inv.paid) revert AlreadyPaid();

        uint256 remaining = inv.amountDue - inv.amountPaid;
        if (msg.value != remaining) revert WrongAmount();

        uint256 fee = (msg.value * feeBps) / 10_000;
        uint256 toIssuer = msg.value - fee;

        inv.amountPaid += uint128(msg.value);
        bool fullySettled = inv.amountPaid >= inv.amountDue;
        inv.paid = fullySettled;

        // Effects done; now interactions.
        if (fee > 0) {
            (bool okFee, ) = payable(feeRecipient).call{value: fee}("");
            if (!okFee) revert TransferFailed();
        }
        (bool okIssuer, ) = payable(inv.issuer).call{value: toIssuer}("");
        if (!okIssuer) revert TransferFailed();

        emit InvoicePaid(id, msg.sender, toIssuer, fee, fullySettled);
    }

    /// @notice Quote helper for the UI: given an invoice, returns (toIssuer, platformFee).
    function quotePayment(uint256 id)
        external
        view
        returns (uint256 total, uint256 toIssuer, uint256 platformFee)
    {
        Invoice storage inv = _invoices[id];
        if (inv.issuer == address(0)) revert InvoiceNotFound();
        total = inv.amountDue - inv.amountPaid;
        platformFee = (total * feeBps) / 10_000;
        toIssuer = total - platformFee;
    }
}
