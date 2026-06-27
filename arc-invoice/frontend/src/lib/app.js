// app.js — shared core for the Attestra on-chain invoice dApp.
//
// No invoice file ever leaves the browser un-processed: hashing, compression and
// chunking all happen client-side, then bytes go straight to the Arc registry contract.
//
// Depends on (loaded via CDN in the HTML):
//   - ethers v6   (window.ethers)
//   - pako        (window.pako)   gzip compression
//   - qrcode      (window.QRCode) QR generation (only on pages that render a QR)

// Load deployment config. We fetch rather than use import-assertions so it works
// in every browser without build tooling. The path resolves relative to this module.
const deployment = await fetch(new URL("./deployment.json", import.meta.url)).then((r) => {
  if (!r.ok) throw new Error("Could not load deployment.json (" + r.status + ")");
  return r.json();
});

export const CHAIN = {
  chainId: deployment.chainId,
  chainIdHex: "0x" + Number(deployment.chainId).toString(16),
  rpcUrl: deployment.rpcUrl,
  explorer: (deployment.explorer || "").replace(/\/+$/, ""),
  registry: deployment.registry,
  feeBps: deployment.feeBps,
  // Block the registry was deployed at — lets the dashboard scan events efficiently.
  deployBlock: Number(deployment.deployBlock || 0),
  name: "Arc Testnet",
  // Native currency descriptor handed to wallets via wallet_addEthereumChain.
  // EVM wallets require nativeCurrency.decimals === 18, so we keep 18 here.
  nativeCurrency: { name: "USD Coin", symbol: "USDC", decimals: 18 },
};

export const ABI = deployment.abi;
export const REGISTRY_ADDRESS = deployment.registry;
export const ZERO_ADDRESS = "0x0000000000000000000000000000000000000000";

export function isRegistryConfigured() {
  return (
    REGISTRY_ADDRESS &&
    REGISTRY_ADDRESS.toLowerCase() !== ZERO_ADDRESS &&
    ethers.isAddress(REGISTRY_ADDRESS)
  );
}

// Max bytes per SSTORE2 data contract. EIP-170 caps contract code at 24,576 bytes;
// we leave headroom for the 1-byte STOP prefix and round down for safety.
export const CHUNK_SIZE = 24_000;

// Invoice amounts are denominated in USDC base units (6 decimals). The same scale
// is used both to parse the amount the issuer types AND for the native msg.value the
// customer sends, so the on-chain `msg.value == amountDue` check is always consistent.
export const USDC_DECIMALS = 6;

// ---------------------------------------------------------------------------
// Provider (read-only)
// ---------------------------------------------------------------------------

let _readProvider = null;
export function getReadProvider() {
  if (!_readProvider) {
    _readProvider = new ethers.JsonRpcProvider(CHAIN.rpcUrl, {
      chainId: CHAIN.chainId,
      name: "arc-testnet",
    });
  }
  return _readProvider;
}

export function getReadContract() {
  return new ethers.Contract(REGISTRY_ADDRESS, ABI, getReadProvider());
}

// ---------------------------------------------------------------------------
// EIP-6963 multi-wallet discovery
// ---------------------------------------------------------------------------
//
// Modern wallets announce themselves via the EIP-6963 event protocol, which lets a
// dApp list *every* installed injected wallet (MetaMask, Rabby, Coinbase, Brave,
// OKX, Trust …) and let the user choose — instead of fighting over a single
// `window.ethereum`. We collect announcements eagerly so they're ready by click time.

const _providersByRdns = new Map(); // rdns -> EIP6963ProviderDetail
const _discoveryListeners = new Set();

const FALLBACK_WALLET_ICON =
  "data:image/svg+xml;utf8," +
  encodeURIComponent(
    `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234d8ee9' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'><rect x='2' y='5' width='20' height='14' rx='3'/><path d='M2 10h20'/><circle cx='17' cy='14' r='1.3' fill='%234d8ee9'/></svg>`
  );
export { FALLBACK_WALLET_ICON };

function _onAnnounce(event) {
  const detail = event.detail;
  if (!detail || !detail.info || !detail.provider) return;
  _providersByRdns.set(detail.info.rdns, detail);
  for (const cb of _discoveryListeners) cb(listWallets());
}

if (typeof window !== "undefined") {
  window.addEventListener("eip6963:announceProvider", _onAnnounce);
  // Ask any wallets that are already loaded to (re)announce.
  window.dispatchEvent(new Event("eip6963:requestProvider"));
}

/** All wallets discovered so far, sorted by name. */
export function listWallets() {
  const found = Array.from(_providersByRdns.values());
  // Fallback: a legacy injected provider that doesn't speak EIP-6963.
  if (found.length === 0 && typeof window !== "undefined" && window.ethereum) {
    found.push({
      info: {
        uuid: "injected-legacy",
        name: window.ethereum.isMetaMask ? "MetaMask" : "Browser Wallet",
        rdns: "injected.legacy",
        icon: FALLBACK_WALLET_ICON,
      },
      provider: window.ethereum,
    });
  }
  return found.sort((a, b) => a.info.name.localeCompare(b.info.name));
}

/** Subscribe to wallet-discovery updates. Returns an unsubscribe function. */
export function onWalletsChanged(cb) {
  _discoveryListeners.add(cb);
  // Re-trigger discovery in case wallets inject late.
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event("eip6963:requestProvider"));
  }
  return () => _discoveryListeners.delete(cb);
}

export function getWalletByRdns(rdns) {
  return _providersByRdns.get(rdns) || null;
}

// ---------------------------------------------------------------------------
// Wallet session manager
// ---------------------------------------------------------------------------
//
// A single source of truth for the connected wallet, shared across all pages.
// Emits "change" so the UI can react to account/network switches and disconnects,
// and remembers the last wallet so we can silently reconnect on the next visit.

const LS_KEY = "attestra:lastWalletRdns";

class WalletSession extends EventTarget {
  constructor() {
    super();
    this.rdns = null;
    this.raw = null; // the EIP-1193 provider object
    this.provider = null; // ethers BrowserProvider
    this.signer = null;
    this.address = null;
    this.chainId = null;
    this._handlers = null;
  }

  get connected() {
    return !!this.address;
  }

  get onArc() {
    return Number(this.chainId) === CHAIN.chainId;
  }

  _emit() {
    this.dispatchEvent(new CustomEvent("change", { detail: this.snapshot() }));
  }

  snapshot() {
    return {
      connected: this.connected,
      address: this.address,
      chainId: this.chainId,
      onArc: this.onArc,
      rdns: this.rdns,
    };
  }

  /** Connect to a specific discovered wallet (EIP-6963 detail). */
  async connect(detail) {
    if (!detail || !detail.provider) throw new Error("No wallet selected.");
    const raw = detail.provider;
    const accounts = await raw.request({ method: "eth_requestAccounts" });
    if (!accounts || accounts.length === 0) throw new Error("No accounts authorized.");

    this._bind(detail.info.rdns, raw);
    await ensureArcNetwork(raw);

    this.address = ethers.getAddress(accounts[0]);
    this.provider = new ethers.BrowserProvider(raw);
    this.signer = await this.provider.getSigner();
    this.chainId = Number((await this.provider.getNetwork()).chainId);
    localStorage.setItem(LS_KEY, detail.info.rdns);
    this._emit();
    return this.snapshot();
  }

  /** Silently reconnect on page load if the wallet still has us authorized. */
  async restore() {
    const rdns = localStorage.getItem(LS_KEY);
    if (!rdns) return null;
    // Give late-injecting wallets a moment to announce themselves.
    let detail = getWalletByRdns(rdns);
    if (!detail) {
      await new Promise((r) => setTimeout(r, 250));
      detail = getWalletByRdns(rdns) || (listWallets().length === 1 ? listWallets()[0] : null);
    }
    if (!detail) return null;
    try {
      const raw = detail.provider;
      const accounts = await raw.request({ method: "eth_accounts" }); // does NOT prompt
      if (!accounts || accounts.length === 0) return null;
      this._bind(detail.info.rdns, raw);
      this.address = ethers.getAddress(accounts[0]);
      this.signer = await this.provider.getSigner();
      this.chainId = Number((await this.provider.getNetwork()).chainId);
      this._emit();
      return this.snapshot();
    } catch {
      return null;
    }
  }

  _bind(rdns, raw) {
    this._unbind();
    this.rdns = rdns;
    this.raw = raw;
    this.provider = new ethers.BrowserProvider(raw);
    this._handlers = {
      accountsChanged: (accounts) => this._onAccountsChanged(accounts),
      chainChanged: () => this._onChainChanged(),
      disconnect: () => this.disconnect(),
    };
    raw.on?.("accountsChanged", this._handlers.accountsChanged);
    raw.on?.("chainChanged", this._handlers.chainChanged);
    raw.on?.("disconnect", this._handlers.disconnect);
  }

  _unbind() {
    if (this.raw && this._handlers && this.raw.removeListener) {
      this.raw.removeListener("accountsChanged", this._handlers.accountsChanged);
      this.raw.removeListener("chainChanged", this._handlers.chainChanged);
      this.raw.removeListener("disconnect", this._handlers.disconnect);
    }
    this._handlers = null;
  }

  async _onAccountsChanged(accounts) {
    if (!accounts || accounts.length === 0) {
      this.disconnect();
      return;
    }
    this.address = ethers.getAddress(accounts[0]);
    try {
      this.signer = await this.provider.getSigner();
    } catch {}
    this._emit();
  }

  async _onChainChanged() {
    // Rebuild provider so ethers picks up the new network cleanly.
    if (this.raw) {
      this.provider = new ethers.BrowserProvider(this.raw);
      try {
        this.chainId = Number((await this.provider.getNetwork()).chainId);
        if (this.address) this.signer = await this.provider.getSigner();
      } catch {}
    }
    this._emit();
  }

  disconnect() {
    this._unbind();
    this.rdns = null;
    this.raw = null;
    this.provider = null;
    this.signer = null;
    this.address = null;
    this.chainId = null;
    localStorage.removeItem(LS_KEY);
    this._emit();
  }

  /** Ensure the wallet is on Arc; switches/adds the network if needed. */
  async ensureArc() {
    if (!this.raw) throw new Error("Wallet not connected.");
    await ensureArcNetwork(this.raw);
    this.provider = new ethers.BrowserProvider(this.raw);
    this.chainId = Number((await this.provider.getNetwork()).chainId);
    if (this.address) this.signer = await this.provider.getSigner();
    this._emit();
  }
}

export const wallet = new WalletSession();

/** Backwards-compatible helper: connect to the only/first wallet without a modal. */
export async function connectWallet() {
  const wallets = listWallets();
  if (wallets.length === 0) {
    throw new Error("No wallet found. Install MetaMask, Rabby, or another EVM wallet.");
  }
  await wallet.connect(wallets[0]);
  return { provider: wallet.provider, signer: wallet.signer, address: wallet.address };
}

export async function ensureArcNetwork(raw) {
  const eth = raw || (typeof window !== "undefined" ? window.ethereum : null);
  if (!eth) throw new Error("No wallet provider available.");
  const currentHex = await eth.request({ method: "eth_chainId" });
  if (parseInt(currentHex, 16) === CHAIN.chainId) return;
  try {
    await eth.request({
      method: "wallet_switchEthereumChain",
      params: [{ chainId: CHAIN.chainIdHex }],
    });
  } catch (err) {
    // 4902 = chain not added yet; some wallets wrap it as -32603.
    if (err && (err.code === 4902 || err.code === -32603)) {
      await eth.request({
        method: "wallet_addEthereumChain",
        params: [
          {
            chainId: CHAIN.chainIdHex,
            chainName: "Arc Testnet",
            rpcUrls: [CHAIN.rpcUrl],
            nativeCurrency: CHAIN.nativeCurrency,
            blockExplorerUrls: [CHAIN.explorer],
          },
        ],
      });
    } else if (err && err.code === 4001) {
      throw new Error("Network switch rejected. Please switch to Arc Testnet.");
    } else {
      throw err;
    }
  }
}

// ---------------------------------------------------------------------------
// File processing: hash (original) -> gzip -> chunk
// ---------------------------------------------------------------------------

export async function processFile(file) {
  if (!file) throw new Error("No file provided.");
  if (file.size === 0) throw new Error("File is empty (0 bytes).");
  const buf = new Uint8Array(await file.arrayBuffer());
  if (buf.length === 0) throw new Error("File is empty (0 bytes).");

  const fileHash = ethers.keccak256(buf); // hash of ORIGINAL bytes (pre-compression)
  const compressed = window.pako.gzip(buf); // gzip for cheaper on-chain storage
  const chunks = [];
  for (let i = 0; i < compressed.length; i += CHUNK_SIZE) {
    chunks.push(ethers.hexlify(compressed.subarray(i, i + CHUNK_SIZE)));
  }
  return {
    fileHash,
    chunks,
    originalSize: buf.length,
    compressedSize: compressed.length,
    chunkCount: chunks.length,
    mimeType: file.type || guessMime(file.name) || "application/octet-stream",
    fileName: file.name || "invoice",
  };
}

// Reassemble: concatenate gzip chunks -> inflate -> original bytes
export function reassembleFile(chunkHexArray) {
  const parts = chunkHexArray.map((h) => ethers.getBytes(h));
  const total = parts.reduce((n, p) => n + p.length, 0);
  const merged = new Uint8Array(total);
  let off = 0;
  for (const p of parts) {
    merged.set(p, off);
    off += p.length;
  }
  return window.pako.ungzip(merged); // back to original bytes
}

// Lightweight extension -> MIME fallback for browsers that don't set file.type.
function guessMime(name = "") {
  const ext = name.split(".").pop()?.toLowerCase();
  const map = {
    pdf: "application/pdf",
    png: "image/png",
    jpg: "image/jpeg",
    jpeg: "image/jpeg",
    gif: "image/gif",
    webp: "image/webp",
    svg: "image/svg+xml",
    txt: "text/plain",
    csv: "text/csv",
    json: "application/json",
    xml: "application/xml",
  };
  return map[ext] || "";
}

// ---------------------------------------------------------------------------
// Cost estimation (shown to the issuer before they pay/confirm)
// ---------------------------------------------------------------------------

export async function estimateRegistrationCost(signer, args) {
  const contract = new ethers.Contract(REGISTRY_ADDRESS, ABI, signer);
  const gas = await contract.registerInvoice.estimateGas(
    args.fileHash,
    args.chunks,
    args.amountDue,
    args.paymentEnabled,
    args.fileName,
    args.mimeType,
    args.metadata
  );
  const feeData = await signer.provider.getFeeData();
  const gasPrice = feeData.gasPrice ?? feeData.maxFeePerGas ?? 0n;
  const weiCost = gas * gasPrice;
  // Gas is paid in the native token (18-decimals value semantics on the wire).
  return { gas, gasPrice, weiCost, usdc: ethers.formatUnits(weiCost, 18) };
}

// ---------------------------------------------------------------------------
// Contract reads
// ---------------------------------------------------------------------------

export async function readInvoiceMeta(provider, id) {
  const contract = new ethers.Contract(REGISTRY_ADDRESS, ABI, provider);
  const m = await contract.getInvoiceMeta(id);
  return {
    id: Number(id),
    issuer: m[0],
    fileHash: m[1],
    chunkCount: Number(m[2]),
    createdAt: Number(m[3]),
    amountDue: m[4],
    amountPaid: m[5],
    paid: m[6],
    paymentEnabled: m[7],
    fileName: m[8],
    mimeType: m[9],
    metadata: m[10],
  };
}

export async function readAllChunks(provider, id, chunkCount, onProgress) {
  const contract = new ethers.Contract(REGISTRY_ADDRESS, ABI, provider);
  const out = [];
  for (let i = 0; i < chunkCount; i++) {
    out.push(await contract.readChunk(id, i)); // hex string
    onProgress?.(i + 1, chunkCount);
  }
  return out;
}

// ---------------------------------------------------------------------------
// Issuer index (for the dashboard) — derived from InvoiceRegistered events.
// ---------------------------------------------------------------------------
//
// The registry indexes the issuer in the InvoiceRegistered event, so we can list
// every invoice an address created by querying logs — no extra contract storage
// needed. We scan in block windows to stay within public-RPC getLogs limits.

export async function getInvoiceIdsByIssuer(provider, issuer, { onProgress } = {}) {
  const contract = new ethers.Contract(REGISTRY_ADDRESS, ABI, provider);
  const latest = await provider.getBlockNumber();
  const from = CHAIN.deployBlock > 0 ? CHAIN.deployBlock : 0;
  const filter = contract.filters.InvoiceRegistered(null, issuer);

  const ids = [];
  const span = Math.max(latest - from, 1);
  let WINDOW = 45_000; // conservative window for public RPCs
  let start = from;
  while (start <= latest) {
    let end = Math.min(start + WINDOW - 1, latest);
    try {
      const logs = await contract.queryFilter(filter, start, end);
      for (const lg of logs) ids.push(Number(lg.args.id));
      onProgress?.(Math.min(end - from + 1, span), span);
      start = end + 1;
    } catch (e) {
      // RPC rejected the range — halve the window and retry the same start.
      if (WINDOW > 1000) {
        WINDOW = Math.floor(WINDOW / 2);
        continue;
      }
      throw e;
    }
  }
  // De-dupe and sort newest-first.
  return Array.from(new Set(ids)).sort((a, b) => b - a);
}

/** Full dashboard payload: the issuer's invoices with live payment status. */
export async function getInvoicesByIssuer(provider, issuer, opts = {}) {
  const ids = await getInvoiceIdsByIssuer(provider, issuer, opts);
  const metas = [];
  for (const id of ids) {
    try {
      metas.push(await readInvoiceMeta(provider, id));
    } catch {
      /* skip unreadable */
    }
  }
  return metas;
}

// ---------------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------------

export function fmtUSDC(baseUnits) {
  return ethers.formatUnits(baseUnits, USDC_DECIMALS);
}
export function parseUSDC(human) {
  return ethers.parseUnits(String(human), USDC_DECIMALS);
}
export function shortAddr(a) {
  return a ? a.slice(0, 6) + "…" + a.slice(-4) : "";
}
export function explorerTx(hash) {
  return `${CHAIN.explorer}/tx/${hash}`;
}
export function explorerAddr(addr) {
  return `${CHAIN.explorer}/address/${addr}`;
}
export function fmtDate(unixSeconds) {
  if (!unixSeconds) return "—";
  return new Date(unixSeconds * 1000).toLocaleString();
}
