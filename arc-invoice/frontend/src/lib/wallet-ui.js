// wallet-ui.js — reusable wallet connection UI shared by every page.
//
// Renders an EIP-6963 wallet picker modal (lists every installed wallet), wires the
// header "Connect wallet" button to it, reflects connection / network state, and
// offers a small account menu (copy address, view on explorer, disconnect).

import {
  wallet,
  listWallets,
  onWalletsChanged,
  shortAddr,
  explorerAddr,
  FALLBACK_WALLET_ICON,
  CHAIN,
} from "./app.js";

// ---------------------------------------------------------------------------
// Modal
// ---------------------------------------------------------------------------

let _modal = null;

function buildModal() {
  if (_modal) return _modal;
  const overlay = document.createElement("div");
  overlay.className = "wallet-modal-overlay hidden";
  overlay.innerHTML = `
    <div class="wallet-modal" role="dialog" aria-modal="true" aria-label="Connect a wallet">
      <div class="wallet-modal-head">
        <h3>Connect a wallet</h3>
        <button class="wallet-modal-close" aria-label="Close">&times;</button>
      </div>
      <div class="wallet-list" id="walletList"></div>
      <p class="wallet-empty hidden" id="walletEmpty">
        No EVM wallet detected. Install
        <a href="https://metamask.io/download/" target="_blank" rel="noopener">MetaMask</a>,
        <a href="https://rabby.io/" target="_blank" rel="noopener">Rabby</a>, or another
        injected wallet, then reopen this dialog.
      </p>
      <div class="wallet-modal-foot">
        New to wallets? You'll need testnet USDC from
        <a href="https://faucet.circle.com" target="_blank" rel="noopener">faucet.circle.com</a>.
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const close = () => hideModal();
  overlay.querySelector(".wallet-modal-close").addEventListener("click", close);
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) close();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !overlay.classList.contains("hidden")) close();
  });

  _modal = overlay;
  return overlay;
}

function renderWalletList(onPick) {
  const overlay = buildModal();
  const list = overlay.querySelector("#walletList");
  const empty = overlay.querySelector("#walletEmpty");
  const wallets = listWallets();

  list.innerHTML = "";
  if (wallets.length === 0) {
    empty.classList.remove("hidden");
    return;
  }
  empty.classList.add("hidden");

  for (const w of wallets) {
    const row = document.createElement("button");
    row.className = "wallet-row";
    row.innerHTML = `
      <img src="${w.info.icon || FALLBACK_WALLET_ICON}" alt="" width="28" height="28" />
      <span class="wallet-name">${escapeHtml(w.info.name)}</span>
      <span class="wallet-chevron">→</span>`;
    row.addEventListener("click", () => onPick(w));
    list.appendChild(row);
  }
}

function showModal() {
  buildModal().classList.remove("hidden");
  document.body.style.overflow = "hidden";
}
function hideModal() {
  if (_modal) _modal.classList.add("hidden");
  document.body.style.overflow = "";
}

let _stopDiscovery = null;

/**
 * Open the wallet picker and resolve once a wallet is connected.
 * Rejects if the user closes the modal or the connection fails.
 */
export function openWalletModal() {
  return new Promise((resolve, reject) => {
    let settled = false;
    const finish = (fn, val) => {
      if (settled) return;
      settled = true;
      _stopDiscovery?.();
      _stopDiscovery = null;
      hideModal();
      fn(val);
    };

    const onPick = async (w) => {
      const list = _modal.querySelector("#walletList");
      list.querySelectorAll(".wallet-row").forEach((b) => (b.disabled = true));
      try {
        await wallet.connect(w);
        finish(resolve, wallet.snapshot());
        // Account is connected and the modal is closed. If we're not on Arc, kick off
        // the add/switch prompt right away — but never block the connection on it.
        if (!wallet.onArc) {
          wallet
            .ensureArc()
            .catch((err) => notify(err?.message || "Switch to Arc Testnet to continue."));
        }
      } catch (e) {
        list.querySelectorAll(".wallet-row").forEach((b) => (b.disabled = false));
        notify(e?.message || "Connection failed");
      }
    };

    renderWalletList(onPick);
    // Keep the list live as wallets inject late.
    _stopDiscovery = onWalletsChanged(() => renderWalletList(onPick));

    // Closing the modal rejects so callers can stop spinners. `once: true` keeps these
    // per-open listeners from accumulating across repeated opens.
    const overlay = buildModal();
    overlay
      .querySelector(".wallet-modal-close")
      .addEventListener("click", () => finish(reject, new Error("cancelled")), { once: true });
    overlay.addEventListener(
      "click",
      (e) => {
        if (e.target === overlay) finish(reject, new Error("cancelled"));
      },
      { once: true }
    );

    showModal();
  });
}

// ---------------------------------------------------------------------------
// Header button controller
// ---------------------------------------------------------------------------

/**
 * Wire a header button to the wallet session. Keeps its label in sync, opens the
 * picker when disconnected, and shows an account menu when connected.
 * Returns an unsubscribe function.
 */
export function mountWalletButton(btn, { onChange } = {}) {
  if (!btn) return () => {};
  ensureNetworkBanner(); // any page with a wallet button gets the wrong-network guide

  const paint = () => {
    const s = wallet.snapshot();
    btn.classList.toggle("btn-primary", s.connected);
    btn.classList.toggle("btn-ghost", !s.connected);
    if (!s.connected) {
      btn.innerHTML = "Connect wallet";
      btn.dataset.state = "disconnected";
    } else if (!s.onArc) {
      btn.innerHTML = `<span class="net-dot bad"></span>Wrong network`;
      btn.dataset.state = "wrongnet";
    } else {
      btn.innerHTML = `<span class="net-dot ok"></span>${shortAddr(s.address)}`;
      btn.dataset.state = "connected";
    }
    onChange?.(s);
  };

  const handleClick = async (e) => {
    e.preventDefault();
    const s = wallet.snapshot();
    if (!s.connected) {
      try {
        await openWalletModal();
      } catch {
        /* user cancelled */
      }
    } else if (!s.onArc) {
      try {
        await wallet.ensureArc();
      } catch (err) {
        notify(err?.message || "Could not switch network");
      }
    } else {
      toggleAccountMenu(btn);
    }
  };

  btn.addEventListener("click", handleClick);
  const off = wallet.addEventListener
    ? (wallet.addEventListener("change", paint), () => wallet.removeEventListener("change", paint))
    : () => {};
  paint();

  return () => {
    btn.removeEventListener("click", handleClick);
    off();
  };
}

// ---------------------------------------------------------------------------
// Wrong-network banner
// ---------------------------------------------------------------------------
//
// When a wallet is connected but on the wrong chain, show a prominent bar with a
// one-click "Add / switch to Arc Testnet" button (adds the network if missing).

let _banner = null;
function ensureNetworkBanner() {
  if (_banner) return _banner;
  const bar = document.createElement("div");
  bar.className = "net-banner hidden";
  bar.innerHTML = `
    <div class="net-banner-inner">
      <span class="net-banner-msg">
        <span class="net-dot bad"></span>
        This dApp runs on <b>Arc Testnet</b> — your wallet is on a different network.
      </span>
      <button class="btn btn-primary btn-sm" id="netAddBtn">Add / switch to Arc Testnet</button>
    </div>`;
  const header = document.querySelector("header.site");
  if (header && header.parentNode) header.insertAdjacentElement("afterend", bar);
  else document.body.prepend(bar);

  const btn = bar.querySelector("#netAddBtn");
  btn.addEventListener("click", async () => {
    const original = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Check your wallet…';
    try {
      await wallet.ensureArc();
      notify("Connected to Arc Testnet");
    } catch (e) {
      notify(e?.message || "Couldn't switch network");
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  const paint = () => {
    const s = wallet.snapshot();
    bar.classList.toggle("hidden", !(s.connected && !s.onArc));
  };
  wallet.addEventListener("change", paint);
  paint();

  _banner = bar;
  return bar;
}

// ---------------------------------------------------------------------------
// Account menu (copy / explorer / disconnect)
// ---------------------------------------------------------------------------

let _menu = null;
function toggleAccountMenu(anchor) {
  if (_menu) {
    closeAccountMenu();
    return;
  }
  const s = wallet.snapshot();
  const menu = document.createElement("div");
  menu.className = "wallet-menu";
  menu.innerHTML = `
    <div class="wallet-menu-addr">${escapeHtml(s.address)}</div>
    <button data-act="copy">Copy address</button>
    <a data-act="explorer" href="${explorerAddr(s.address)}" target="_blank" rel="noopener">View on explorer</a>
    <button data-act="disconnect" class="danger">Disconnect</button>`;
  document.body.appendChild(menu);

  const r = anchor.getBoundingClientRect();
  menu.style.top = `${r.bottom + window.scrollY + 8}px`;
  menu.style.right = `${Math.max(12, window.innerWidth - r.right)}px`;

  menu.querySelector('[data-act="copy"]').addEventListener("click", async () => {
    try {
      await navigator.clipboard.writeText(s.address);
      notify("Address copied");
    } catch {}
    closeAccountMenu();
  });
  menu.querySelector('[data-act="disconnect"]').addEventListener("click", () => {
    wallet.disconnect();
    closeAccountMenu();
  });
  menu.querySelector('[data-act="explorer"]').addEventListener("click", closeAccountMenu);

  setTimeout(() => document.addEventListener("click", outsideClose), 0);
  function outsideClose(e) {
    if (!menu.contains(e.target) && e.target !== anchor) closeAccountMenu();
  }
  menu._outsideClose = outsideClose;
  _menu = menu;
}

function closeAccountMenu() {
  if (!_menu) return;
  document.removeEventListener("click", _menu._outsideClose);
  _menu.remove();
  _menu = null;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function escapeHtml(s = "") {
  return s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

// Minimal toast that reuses an existing #toast element if the page has one.
export function notify(msg) {
  let t = document.getElementById("toast");
  if (!t) {
    t = document.createElement("div");
    t.id = "toast";
    t.className = "toast";
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.classList.add("show");
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove("show"), 2800);
}

/** Try to restore a previous session silently. Safe to call on every page. */
export async function autoConnect() {
  try {
    return await wallet.restore();
  } catch {
    return null;
  }
}

export { CHAIN };
