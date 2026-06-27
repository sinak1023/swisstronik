// issue.js — issuer flow controller.
import {
  wallet,
  processFile,
  estimateRegistrationCost,
  isRegistryConfigured,
  REGISTRY_ADDRESS,
  ABI,
  CHAIN,
  parseUSDC,
  explorerTx,
} from "./lib/app.js";
import { mountWalletButton, notify as toast, autoConnect } from "./lib/wallet-ui.js";

const MAX_BYTES = 5 * 1024 * 1024; // 5 MB hard cap

const el = (id) => document.getElementById(id);
const state = {
  processed: null, // result of processFile
  registerArgs: null,
  sealed: false,
};

// ---------- wallet ----------
mountWalletButton(el("connectBtn"), {
  onChange: (s) => {
    el("needWallet").classList.toggle("hidden", s.connected && s.onArc);
    if (s.connected && !s.onArc) {
      el("needWallet").textContent =
        "Wrong network — click the wallet button to switch to Arc Testnet.";
    } else {
      el("needWallet").textContent =
        "Connect your wallet (top right) to estimate the cost and register.";
    }
    refreshEstimateButton();
  },
});
autoConnect();

if (!isRegistryConfigured()) {
  toast("Registry address not set — deploy the contract first.");
}

// ---------- file handling ----------
const drop = el("drop");
const fileInput = el("fileInput");
let dragDepth = 0;

drop.addEventListener("click", () => fileInput.click());
drop.addEventListener("keydown", (e) => {
  if (e.key === "Enter" || e.key === " ") {
    e.preventDefault();
    fileInput.click();
  }
});
drop.addEventListener("dragenter", (e) => {
  e.preventDefault();
  dragDepth++;
  drop.classList.add("dragover");
});
drop.addEventListener("dragover", (e) => e.preventDefault());
drop.addEventListener("dragleave", (e) => {
  e.preventDefault();
  dragDepth = Math.max(0, dragDepth - 1);
  if (dragDepth === 0) drop.classList.remove("dragover");
});
drop.addEventListener("drop", (e) => {
  e.preventDefault();
  dragDepth = 0;
  drop.classList.remove("dragover");
  if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener("change", (e) => {
  if (e.target.files.length) handleFile(e.target.files[0]);
  // Reset so selecting the SAME file again still fires `change`.
  e.target.value = "";
});

async function handleFile(file) {
  if (file.size > MAX_BYTES) {
    toast(`File too large (${humanSize(file.size)}). Max 5 MB.`);
    return;
  }
  // A new file invalidates any prior estimate/seal flow.
  resetFlow();
  el("fileSummary").classList.add("hidden");
  toast("Processing in browser…");
  try {
    const p = await processFile(file);
    state.processed = p;
    el("sName").textContent = p.fileName;
    el("sType").textContent = p.mimeType;
    el("sOrig").textContent = humanSize(p.originalSize);
    el("sComp").textContent = humanSize(p.compressedSize);
    el("sChunks").textContent = p.chunkCount;
    el("sHash").textContent = p.fileHash;
    el("fileSummary").classList.remove("hidden");
    refreshEstimateButton();
  } catch (e) {
    state.processed = null;
    refreshEstimateButton();
    toast("Could not process file: " + (e.message || e));
  }
}

// Reset the estimate/seal UI when inputs change so a stale cost can't be submitted.
function resetFlow() {
  state.registerArgs = null;
  state.sealed = false;
  el("costBox").classList.add("hidden");
  el("sealBtn").classList.add("hidden");
  el("sealBtn").disabled = false;
  el("sealBtn").textContent = "Pay & seal on Arc";
  el("estimateBtn").classList.remove("hidden");
  el("estimateBtn").textContent = "Estimate cost";
  el("log").classList.add("hidden");
  el("log").innerHTML = "";
  refreshEstimateButton();
}

function humanSize(n) {
  if (n < 1024) return n + " B";
  if (n < 1024 * 1024) return (n / 1024).toFixed(1) + " KB";
  return (n / 1024 / 1024).toFixed(2) + " MB";
}

// ---------- payment toggle ----------
el("enablePay").addEventListener("change", (e) => {
  el("payFields").style.display = e.target.checked ? "flex" : "none";
  resetFlow();
  updateNet();
});
el("invAmount").addEventListener("input", () => {
  resetFlow();
  updateNet();
});
// Re-estimate if invoice details change after a prior estimate.
["invNumber", "invCustomer", "invNote"].forEach((id) =>
  el(id).addEventListener("input", () => {
    if (state.registerArgs) resetFlow();
  })
);

function updateNet() {
  const amt = parseFloat(el("invAmount").value || "0");
  if (!amt || amt <= 0) {
    el("invNet").value = "—";
    el("feeHint").textContent = "";
    return;
  }
  const feePct = CHAIN.feeBps / 100;
  const fee = (amt * CHAIN.feeBps) / 10000;
  const net = amt - fee;
  el("invNet").value = net.toFixed(2) + " USDC";
  el("feeHint").textContent = `Platform fee ${feePct}% (${fee.toFixed(2)} USDC) is split on-chain.`;
}

// ---------- estimate button enable ----------
function refreshEstimateButton() {
  const ready = wallet.connected && wallet.onArc && state.processed && !state.sealed;
  el("estimateBtn").disabled = !ready;
}

// ---------- build register args ----------
function buildArgs() {
  const p = state.processed;
  if (!p) throw new Error("Add an invoice file first.");
  const enablePay = el("enablePay").checked;
  let amountDue = 0n;
  if (enablePay) {
    const amt = el("invAmount").value;
    if (!amt || parseFloat(amt) <= 0) throw new Error("Enter a valid amount due.");
    amountDue = parseUSDC(amt);
  }
  const metadata = JSON.stringify({
    number: el("invNumber").value.trim(),
    customer: el("invCustomer").value.trim(),
    note: el("invNote").value.trim(),
    currency: "USDC",
    v: 1,
  });
  return {
    fileHash: p.fileHash,
    chunks: p.chunks,
    amountDue,
    paymentEnabled: enablePay,
    fileName: p.fileName,
    mimeType: p.mimeType,
    metadata,
  };
}

// ---------- estimate ----------
el("estimateBtn").addEventListener("click", async () => {
  if (!wallet.connected) return toast("Connect your wallet first.");
  if (!wallet.onArc) {
    try {
      await wallet.ensureArc();
    } catch (e) {
      return toast(e.message || "Switch to Arc Testnet to continue.");
    }
  }
  try {
    el("estimateBtn").disabled = true;
    el("estimateBtn").innerHTML = '<span class="spinner"></span> Estimating…';
    const args = buildArgs();
    state.registerArgs = args;
    const est = await estimateRegistrationCost(wallet.signer, args);
    el("cChunks").textContent = args.chunks.length;
    el("cGas").textContent = est.gas.toString();
    el("cTotal").textContent = Number(est.usdc).toFixed(4) + " USDC";
    el("costBox").classList.remove("hidden");
    el("estimateBtn").classList.add("hidden");
    el("sealBtn").classList.remove("hidden");
  } catch (e) {
    toast(e.reason || e.shortMessage || e.message || "Estimate failed");
    el("estimateBtn").disabled = false;
    el("estimateBtn").textContent = "Estimate cost";
  }
});

// ---------- log ----------
function logStep(text, cls = "active") {
  const log = el("log");
  log.classList.remove("hidden");
  log.querySelectorAll(".l.active").forEach((n) => {
    n.classList.remove("active");
    n.classList.add("done");
  });
  const line = document.createElement("div");
  line.className = "l " + cls;
  line.textContent = text;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}

// ---------- seal (register) ----------
el("sealBtn").addEventListener("click", async () => {
  if (!wallet.connected) return toast("Connect your wallet first.");
  if (!wallet.onArc) {
    try {
      await wallet.ensureArc();
    } catch (e) {
      return toast(e.message || "Switch to Arc Testnet to continue.");
    }
  }
  let args;
  try {
    args = state.registerArgs || buildArgs();
  } catch (e) {
    return toast(e.message);
  }
  try {
    el("sealBtn").disabled = true;
    el("sealBtn").innerHTML = '<span class="spinner"></span> Sealing on Arc…';

    logStep(`Storing ${args.chunks.length} chunk(s) on-chain via SSTORE2…`);
    const contract = new ethers.Contract(REGISTRY_ADDRESS, ABI, wallet.signer);
    const tx = await contract.registerInvoice(
      args.fileHash,
      args.chunks,
      args.amountDue,
      args.paymentEnabled,
      args.fileName,
      args.mimeType,
      args.metadata
    );
    logStep("Transaction sent: " + tx.hash);
    const receipt = await tx.wait();
    logStep("Confirmed in block " + receipt.blockNumber, "done");

    // Parse the InvoiceRegistered event to get the id.
    let invoiceId = null;
    for (const lg of receipt.logs) {
      try {
        const parsed = contract.interface.parseLog(lg);
        if (parsed && parsed.name === "InvoiceRegistered") {
          invoiceId = parsed.args.id.toString();
          break;
        }
      } catch (_) {}
    }
    if (invoiceId === null) {
      invoiceId = (await contract.invoiceCount()).toString(); // fallback
    }

    state.sealed = true;
    showResult(invoiceId, tx.hash);
  } catch (e) {
    logStep("Failed: " + (e.reason || e.shortMessage || e.message), "bad");
    toast(e.reason || e.shortMessage || "Transaction failed");
    el("sealBtn").disabled = false;
    el("sealBtn").textContent = "Pay & seal on Arc";
  }
});

// ---------- result + QR ----------
function showResult(invoiceId, txHash) {
  const base = location.href.replace(/issue\.html.*$/, "").replace(/\/?$/, "/");
  const link = `${base}verify.html?id=${invoiceId}`;
  el("rId").textContent = invoiceId;
  el("rLink").textContent = link;
  el("rOpen").href = link;
  el("rTx").href = explorerTx(txHash);

  // QR — qrcodejs renders synchronously into a holder div; move the result node over.
  const box = el("qr").parentElement;
  box.innerHTML = "";
  if (window.QRCode) {
    const holder = document.createElement("div");
    new QRCode(holder, {
      text: link,
      width: 220,
      height: 220,
      correctLevel: QRCode.CorrectLevel.M,
    });
    const made = holder.querySelector("canvas") || holder.querySelector("img");
    if (made) box.appendChild(made);
  } else {
    box.textContent = "QR unavailable";
  }

  el("copyLink").onclick = async () => {
    try {
      await navigator.clipboard.writeText(link);
      toast("Link copied");
    } catch {
      toast("Copy failed — select the link manually.");
    }
  };

  el("cardResult").classList.remove("hidden");
  el("cardResult").scrollIntoView({ behavior: "smooth" });
}
