// dashboard.js — "My invoices" profile page.
//
// Lists every invoice issued from the connected wallet (read from Arc via the
// InvoiceRegistered event index) with live settlement status, so an issuer can see
// at a glance which invoices have been paid.
import {
  wallet,
  getReadProvider,
  getInvoicesByIssuer,
  isRegistryConfigured,
  fmtUSDC,
  fmtDate,
} from "./lib/app.js";
import { mountWalletButton, notify as toast, autoConnect, openWalletModal } from "./lib/wallet-ui.js";

const el = (id) => document.getElementById(id);
const state = { loading: false, address: null };

function show(id) { el(id).classList.remove("hidden"); }
function hide(id) { el(id).classList.add("hidden"); }
function hideAll() {
  ["cardConnect", "cardLoading", "stats", "cardEmpty", "listWrap", "cardError"].forEach(hide);
}
function setBar(pct) { el("loadBar").style.width = pct + "%"; }

// ---------- wallet wiring ----------
mountWalletButton(el("connectBtn"), { onChange: onWalletChange });
el("connectBtn2").addEventListener("click", () => openWalletModal().catch(() => {}));
el("refreshBtn").addEventListener("click", () => load(true));
el("retryBtn").addEventListener("click", () => load(true));
autoConnect();

function onWalletChange(s) {
  el("refreshBtn").classList.toggle("hidden", !s.connected);
  if (!s.connected) {
    state.address = null;
    hideAll();
    show("cardConnect");
    el("dashSub").textContent =
      "Every invoice issued from your connected wallet, with live settlement status.";
    return;
  }
  // Reload when the address actually changes (account switch) or first connect.
  if (s.address !== state.address) {
    state.address = s.address;
    load();
  }
}

// ---------- load ----------
async function load(force = false) {
  if (!wallet.connected) {
    hideAll();
    show("cardConnect");
    return;
  }
  if (state.loading && !force) return;
  if (!isRegistryConfigured()) {
    hideAll();
    show("cardError");
    el("errMsg").textContent = "Registry address not configured. Deploy the contract first.";
    return;
  }

  state.loading = true;
  hideAll();
  show("cardLoading");
  setBar(6);
  el("dashSub").textContent = `Wallet ${wallet.address}`;

  try {
    const provider = getReadProvider();
    const invoices = await getInvoicesByIssuer(provider, wallet.address, {
      onProgress: (done, total) => setBar(6 + Math.round((84 * done) / Math.max(total, 1))),
    });
    setBar(100);
    render(invoices);
  } catch (e) {
    hideAll();
    show("cardError");
    el("errMsg").textContent = e.reason || e.shortMessage || e.message || "Unknown error.";
  } finally {
    state.loading = false;
  }
}

// ---------- render ----------
function render(invoices) {
  hideAll();

  if (invoices.length === 0) {
    show("cardEmpty");
    return;
  }

  // stats
  let payable = 0, paid = 0, outstanding = 0n;
  for (const inv of invoices) {
    if (inv.paymentEnabled) {
      payable++;
      if (inv.paid) paid++;
      else outstanding += inv.amountDue - inv.amountPaid;
    }
  }
  el("stCount").textContent = invoices.length;
  el("stPayable").textContent = payable;
  el("stPaid").textContent = paid;
  el("stOutstanding").textContent = fmtUSDC(outstanding);
  show("stats");

  // list
  const list = el("invList");
  list.innerHTML = "";
  for (const inv of invoices) list.appendChild(invoiceCard(inv));
  show("listWrap");
}

function invoiceCard(inv) {
  let md = {};
  try { md = JSON.parse(inv.metadata || "{}"); } catch (_) {}

  const verifyUrl = `${location.origin}${location.pathname.replace(/dashboard\.html.*$/, "")}verify.html?id=${inv.id}`;

  // status pill
  let statusHtml;
  if (!inv.paymentEnabled) {
    statusHtml = `<span class="pill muted-pill"><span class="dot"></span>No payment</span>`;
  } else if (inv.paid) {
    statusHtml = `<span class="pill ok"><span class="dot"></span>Paid</span>`;
  } else if (inv.amountPaid > 0n) {
    statusHtml = `<span class="pill warn"><span class="dot"></span>Partly paid</span>`;
  } else {
    statusHtml = `<span class="pill warn"><span class="dot"></span>Awaiting payment</span>`;
  }

  const amountHtml = inv.paymentEnabled
    ? `<div class="inv-amount">${fmtUSDC(inv.amountDue)} <span class="unit">USDC</span></div>
       ${inv.paymentEnabled && !inv.paid && inv.amountPaid > 0n
         ? `<div class="inv-sub">Paid ${fmtUSDC(inv.amountPaid)} of ${fmtUSDC(inv.amountDue)}</div>`
         : ""}`
    : `<div class="inv-amount muted">—</div>`;

  const card = document.createElement("div");
  card.className = "inv-card";
  card.innerHTML = `
    <div class="inv-main">
      <div class="inv-top">
        <span class="inv-id">#${inv.id}</span>
        <span class="inv-title">${esc(md.number ? "Invoice " + md.number : inv.fileName || "Invoice")}</span>
        ${statusHtml}
      </div>
      <div class="inv-meta">
        ${md.customer ? `<span>${esc(md.customer)}</span>` : ""}
        <span title="${esc(inv.fileName || "")}">${esc(inv.fileName || "file")}</span>
        <span>${fmtDate(inv.createdAt)}</span>
      </div>
    </div>
    <div class="inv-right">
      ${amountHtml}
      <div class="inv-actions">
        <button class="btn btn-ghost btn-sm" data-act="copy">Copy link</button>
        <a class="btn btn-ghost btn-sm" href="${verifyUrl}" target="_blank" rel="noopener">Open</a>
      </div>
    </div>`;

  card.querySelector('[data-act="copy"]').addEventListener("click", async () => {
    try {
      await navigator.clipboard.writeText(verifyUrl);
      toast("Verify link copied");
    } catch {
      toast("Copy failed");
    }
  });

  return card;
}

function esc(s = "") {
  return String(s).replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])
  );
}
