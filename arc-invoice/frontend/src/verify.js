// verify.js — customer / verifier flow.
import {
  wallet,
  getReadProvider,
  readInvoiceMeta,
  reassembleFile,
  isRegistryConfigured,
  REGISTRY_ADDRESS,
  ABI,
  CHAIN,
  fmtUSDC,
  shortAddr,
  explorerAddr,
  fmtDate,
} from "./lib/app.js";
import { mountWalletButton, notify as toast, autoConnect, openWalletModal } from "./lib/wallet-ui.js";

const el = (id) => document.getElementById(id);
const state = { invoiceId: null, meta: null, fileBytes: null, fileBlobUrl: null };

function show(id) { el(id).classList.remove("hidden"); }
function hide(id) { el(id).classList.add("hidden"); }

function loadLog(text, cls = "active") {
  const log = el("loadLog");
  log.querySelectorAll(".l.active").forEach((n) => { n.classList.remove("active"); n.classList.add("done"); });
  const line = document.createElement("div");
  line.className = "l " + cls;
  line.textContent = text;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}
function setBar(pct) { el("loadBar").style.width = pct + "%"; }

// ---------- wallet ----------
mountWalletButton(el("connectBtn"), {
  onChange: () => {
    // If the invoice is loaded and payable, keep the pay button in sync.
    if (state.meta && state.meta.paymentEnabled && !state.meta.paid) {
      if (wallet.connected && wallet.onArc) enablePayButton();
      else {
        const btn = el("payBtn");
        btn.disabled = false;
        btn.textContent = wallet.connected ? "Switch to Arc to pay" : "Connect wallet to pay";
        btn.onclick = doPay;
      }
    }
  },
});
autoConnect();

// ---------- lookup ----------
el("lookupBtn").addEventListener("click", () => {
  const id = el("lookupId").value;
  if (!id || Number(id) < 1) { toast("Enter a valid invoice ID"); return; }
  location.search = "?id=" + id;
});
el("lookupId").addEventListener("keydown", (e) => { if (e.key === "Enter") el("lookupBtn").click(); });

// ---------- main: load by ?id ----------
(async function init() {
  const params = new URLSearchParams(location.search);
  const id = params.get("id");
  if (!id) { show("cardLookup"); return; }
  if (!/^\d+$/.test(id) || Number(id) < 1) {
    show("cardError");
    el("errMsg").textContent = "Invalid invoice ID.";
    return;
  }
  state.invoiceId = id;
  hide("cardLookup");
  show("cardLoading");
  try {
    await loadInvoice(id);
  } catch (e) {
    hide("cardLoading");
    show("cardError");
    el("errMsg").textContent = e.reason || e.shortMessage || e.message || "Unknown error.";
  }
})();

async function loadInvoice(id) {
  if (!isRegistryConfigured()) {
    throw new Error("Registry address not configured. Deploy the contract and rebuild.");
  }
  const provider = getReadProvider();

  loadLog("Reading invoice metadata from Arc…");
  setBar(8);
  const meta = await readInvoiceMeta(provider, id);
  state.meta = meta;
  loadLog(`Found invoice by ${shortAddr(meta.issuer)} · ${meta.chunkCount} chunk(s)`, "done");

  if (meta.chunkCount === 0) {
    throw new Error("Invoice has no stored file chunks.");
  }
  el("loadDesc").textContent =
    meta.chunkCount <= 1
      ? "Small file — loading in a single read."
      : `Larger file — streaming ${meta.chunkCount} chunks from chain.`;

  loadLog("Fetching file chunks…");
  const chunkHexes = [];
  const contract = new ethers.Contract(REGISTRY_ADDRESS, ABI, provider);
  for (let i = 0; i < meta.chunkCount; i++) {
    const hex = await contract.readChunk(id, i);
    chunkHexes.push(hex);
    setBar(8 + Math.round((82 * (i + 1)) / meta.chunkCount));
    loadLog(`  chunk ${i + 1}/${meta.chunkCount} loaded`, "done");
  }

  loadLog("Decompressing & verifying integrity…");
  setBar(94);
  let fileBytes, recomputed, authentic;
  try {
    fileBytes = reassembleFile(chunkHexes);
    state.fileBytes = fileBytes;
    recomputed = ethers.keccak256(fileBytes);
    authentic = recomputed.toLowerCase() === meta.fileHash.toLowerCase();
  } catch (e) {
    // Decompression failure means the on-chain bytes can't form the original file.
    setBar(100);
    loadLog("Integrity check FAILED ✗ — file could not be decoded.", "bad");
    renderInvoice(meta, null, "0x", false);
    return;
  }
  setBar(100);
  loadLog(authentic ? "Integrity check passed ✓" : "Integrity check FAILED ✗", authentic ? "done" : "bad");

  renderInvoice(meta, fileBytes, recomputed, authentic);
}

function renderInvoice(meta, fileBytes, recomputed, authentic) {
  hide("cardLoading");
  show("cardInvoice");

  // authenticity pill
  const pill = el("authPill");
  if (authentic) {
    pill.className = "pill ok";
    el("authText").textContent = "Authentic — matches on-chain seal";
  } else {
    pill.className = "pill bad";
    el("authText").textContent = "Tampered — does not match on-chain seal";
  }

  // metadata
  let md = {};
  try { md = JSON.parse(meta.metadata || "{}"); } catch (_) {}
  el("invTitle").textContent = md.number ? `Invoice #${md.number}` : meta.fileName || "Invoice";
  el("mNumber").textContent = md.number || "—";
  el("mCustomer").textContent = md.customer || "—";
  el("mNote").textContent = md.note || "—";
  el("mIssuer").innerHTML = `<a href="${explorerAddr(meta.issuer)}" target="_blank" rel="noopener">${meta.issuer}</a>`;
  el("mDate").textContent = fmtDate(meta.createdAt);
  el("mHash").textContent = meta.fileHash;
  el("mRecomputed").textContent = recomputed;
  el("viewTxAddr").href = explorerAddr(REGISTRY_ADDRESS);

  // preview + download (only if we recovered the bytes)
  if (fileBytes) {
    renderPreview(meta, fileBytes);
    const blob = new Blob([fileBytes], { type: meta.mimeType || "application/octet-stream" });
    state.fileBlobUrl = URL.createObjectURL(blob);
    const dl = el("downloadBtn");
    dl.disabled = false;
    dl.onclick = () => {
      const a = document.createElement("a");
      a.href = state.fileBlobUrl;
      a.download = meta.fileName || "invoice";
      a.click();
    };
  } else {
    el("preview").innerHTML = `<pre>File bytes could not be recovered from chain.</pre>`;
    el("downloadBtn").disabled = true;
  }

  // payment
  if (meta.paymentEnabled) {
    show("paySection");
    updatePaymentUI();
  }
}

function updatePaymentUI() {
  const meta = state.meta;
  const total = meta.amountDue - meta.amountPaid;
  const fee = (total * BigInt(CHAIN.feeBps)) / 10000n;
  const toIssuer = total - fee;
  el("pDue").textContent = fmtUSDC(meta.amountDue) + " USDC";
  el("pIssuer").textContent = fmtUSDC(toIssuer) + " USDC";
  el("pFee").textContent = fmtUSDC(fee) + ` USDC (${CHAIN.feeBps / 100}%)`;
  el("pTotal").textContent = fmtUSDC(total) + " USDC";

  const btn = el("payBtn");
  if (meta.paid) {
    show("paidPill");
    btn.disabled = true;
    btn.textContent = "Already paid";
    btn.onclick = null;
  } else if (wallet.connected && wallet.onArc) {
    enablePayButton();
  } else {
    btn.disabled = false;
    btn.textContent = wallet.connected ? "Switch to Arc to pay" : "Connect wallet to pay";
    btn.onclick = doPay;
  }
}

function renderPreview(meta, fileBytes) {
  const box = el("preview");
  const mime = meta.mimeType || "";
  if (mime.startsWith("image/")) {
    const url = URL.createObjectURL(new Blob([fileBytes], { type: mime }));
    box.innerHTML = "";
    const img = document.createElement("img");
    img.src = url;
    img.alt = "invoice";
    box.appendChild(img);
  } else if (mime === "application/pdf") {
    const url = URL.createObjectURL(new Blob([fileBytes], { type: mime }));
    box.innerHTML = `<iframe src="${url}" style="width:100%;height:520px;border:0;" title="invoice"></iframe>`;
  } else if (mime.startsWith("text/") || mime === "application/json" || mime === "application/xml") {
    const text = new TextDecoder().decode(fileBytes);
    const pre = document.createElement("pre");
    pre.textContent = text;
    box.innerHTML = "";
    box.appendChild(pre);
  } else {
    box.innerHTML = `<pre>Binary file (${mime || "unknown type"}). Use “Download original file” to view.</pre>`;
  }
}

// ---------- pay ----------
function enablePayButton() {
  const btn = el("payBtn");
  btn.disabled = false;
  btn.textContent = `Pay ${fmtUSDC(state.meta.amountDue - state.meta.amountPaid)} USDC`;
  btn.onclick = doPay;
}

function payLog(text, cls = "active") {
  const log = el("payLog");
  log.classList.remove("hidden");
  log.querySelectorAll(".l.active").forEach((n) => { n.classList.remove("active"); n.classList.add("done"); });
  const line = document.createElement("div");
  line.className = "l " + cls;
  line.textContent = text;
  log.appendChild(line);
}

async function doPay() {
  if (!wallet.connected) {
    try {
      await openWalletModal();
    } catch {
      return;
    }
  }
  if (!wallet.onArc) {
    try {
      await wallet.ensureArc();
    } catch (e) {
      return toast(e.message || "Switch to Arc Testnet to pay.");
    }
  }
  const btn = el("payBtn");
  try {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Paying…';
    const contract = new ethers.Contract(REGISTRY_ADDRESS, ABI, wallet.signer);

    // Re-read live state so we never send a stale amount (e.g. partially paid).
    const fresh = await readInvoiceMeta(getReadProvider(), state.invoiceId);
    state.meta = fresh;
    if (fresh.paid) {
      updatePaymentUI();
      return toast("Invoice is already paid.");
    }
    const total = fresh.amountDue - fresh.amountPaid;

    payLog("Sending payment of " + fmtUSDC(total) + " USDC…");
    const tx = await contract.payInvoice(state.invoiceId, { value: total });
    payLog("Transaction sent: " + tx.hash);
    await tx.wait();
    payLog("Payment settled ✓ — issuer and platform paid atomically.", "done");

    // Refresh on-chain state and repaint.
    state.meta = await readInvoiceMeta(getReadProvider(), state.invoiceId);
    updatePaymentUI();
    toast("Invoice paid");
  } catch (e) {
    payLog("Payment failed: " + (e.reason || e.shortMessage || e.message), "bad");
    toast(e.reason || e.shortMessage || "Payment failed");
    updatePaymentUI();
  }
}
