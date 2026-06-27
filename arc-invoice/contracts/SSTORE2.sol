// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

/// @title SSTORE2
/// @notice Read and write data chunks as contract bytecode. Storing bytes as the
///         runtime code of a tiny "data contract" is ~10x cheaper than SSTORE into
///         contract storage, and reading back is a cheap EXTCODECOPY.
/// @dev    Based on the well-known SSTORE2 pattern (0xSequence / Solmate lineage),
///         reimplemented here so the project is self-contained.
library SSTORE2 {
    // The deployed data contract starts with a single STOP byte (0x00) so the
    // stored payload can never be executed as code. Actual data begins at offset 1.
    uint256 internal constant DATA_OFFSET = 1;

    error WriteError();
    error OutOfBounds();

    /// @notice Writes `data` as the bytecode of a freshly deployed contract.
    /// @return pointer The address of the deployed data contract.
    function write(bytes memory data) internal returns (address pointer) {
        // Creation code that, when run, returns `0x00 ++ data` as the contract's
        // runtime code. Layout of the init code:
        //   0x00  0x61 PUSH2 <runtimeLength>
        //   0x03  0x80 DUP1
        //   0x04  0x60 PUSH1 0x0a (offset where runtime code begins in init code)
        //   0x06  0x3D RETURNDATASIZE (cheap 0)
        //   0x07  0x39 CODECOPY
        //   0x08  0x3D RETURNDATASIZE (cheap 0)
        //   0x09  0xF3 RETURN
        //   0x0a  0x00 STOP   <-- runtime code: leading STOP then data
        uint256 runtimeLength = data.length + 1; // +1 for the leading STOP byte

        bytes memory initCode = abi.encodePacked(
            hex"61",            // PUSH2
            uint16(runtimeLength),
            hex"80_60_0a_3d_39_3d_f3_00",
            data
        );

        assembly {
            pointer := create(0, add(initCode, 0x20), mload(initCode))
        }
        if (pointer == address(0)) revert WriteError();
    }

    /// @notice Reads the full payload stored at `pointer`.
    function read(address pointer) internal view returns (bytes memory) {
        return readBytecode(pointer, DATA_OFFSET, _codeSize(pointer) - DATA_OFFSET);
    }

    function _codeSize(address pointer) private view returns (uint256 size) {
        assembly {
            size := extcodesize(pointer)
        }
    }

    function readBytecode(address pointer, uint256 start, uint256 size)
        private
        view
        returns (bytes memory data)
    {
        assembly {
            data := mload(0x40)
            // round up to 32 and reserve room for length + data
            mstore(0x40, add(data, and(add(add(size, 0x20), 0x1f), not(0x1f))))
            mstore(data, size)
            extcodecopy(pointer, add(data, 0x20), start, size)
        }
    }
}
