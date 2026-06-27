# Attestra — On-chain invoice authenticity & settlement on Arc

Attestra puts invoices **directly on Circle's Arc network**. The file itself, its
cryptographic fingerprint, and an optional pay button all live on-chain. Anyone can:

1. **Verify** an invoice is authentic (the original file is rebuilt from chain data and
   its hash is checked against the on-chain seal), and
2. **Pay** it in native USDC, with the platform fee split atomically by the contract.

**No invoice file is ever stored on the server.** Hashing, compression, and chunking all
happen in the user's browser; the bytes go straight from the browser to the Arc chain.

---

## Why this fits Arc

Arc is Circle's stablecoin-native L1, built for financial settlement: USDC is the native
gas token, fees are dollar-denominated and predictable, and finality is sub-second.
Attestra uses these properties directly:

- **Tamper-proof invoices** address real invoice-fraud losses (forged / altered / double-paid invoices).
- **Atomic USDC settlement** — payment and fee-split happen in one transaction; the
  platform never custodies funds. This is exactly the "peer-to-peer payments + settlement"
  use case Arc highlights.
- **Dollar-denominated gas** means the cost shown to the user before sealing is stable.

---

## Architecture (Model C — hybrid)

```
Issuer browser                          Arc chain                       Verifier browser
──────────────                          ─────────                       ────────────────
file ──► keccak256 (fingerprint) ─┐
     └─► gzip ─► split into        │     ┌─────────────────────────┐
         <24KB chunks ─────────────┼────►│ ArcInvoiceRegistry      │
                                   │     │  • fileHash (seal)      │◄── readChunk(id, i)
         pay USDC for gas ─────────┘     │  • chunk pointers       │     reassemble ► gunzip
                                         │    (SSTORE2 data        │     recompute hash
                                         │     contracts)          │     compare ► ✓ / ✗
                                         │  • metadata             │
                                         │  • payInvoice() split   │◄── payInvoice{value}
                                         └─────────────────────────┘     98% ► issuer
                                                                          2%  ► platform
```

- **`contracts/SSTORE2.sol`** — stores each chunk as the bytecode of a tiny data contract.
  ~10× cheaper than SSTORE into storage; read back via `EXTCODECOPY`.
- **`contracts/ArcInvoiceRegistry.sol`** — registers invoices (hash + chunk pointers +
  metadata), exposes free reads, and does the atomic payment split (capped at 5% so the
  owner can't rug users).

Each chunk is capped at 24,000 bytes (under the EIP-170 24,576-byte contract-code limit).
Large files are split across multiple chunks and streamed back chunk-by-chunk on the
verify page (with a progress bar) to avoid RPC response-size limits.

---

## Network details (Arc Testnet)

| Field | Value |
|---|---|
| RPC URL | `https://rpc.testnet.arc.network` |
| Chain ID | `5042002` |
| Native gas token | USDC (`0x3600000000000000000000000000000000000000`) |
| Explorer | `https://testnet.arcscan.app` |
| Faucet | `https://faucet.circle.com` (select Arc Testnet) |

> Testnet USDC has no real-world value. This is a demo.

---

## Prerequisites (Ubuntu 22.04)

```bash
# Node.js 20+ (the project was built/tested on Node 22)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

node --version   # should print v20+ or v22+
```

---

## 1. Install

```bash
cd arc-invoice
npm install
```

## 2. Compile the contracts (offline-friendly)

The repo ships with pre-compiled artifacts in `artifacts-solc/`. To recompile:

```bash
node compile.js
# ✅ ArcInvoiceRegistry  (deployed size: ~4949 bytes)
# ✅ SSTORE2             (deployed size: ~57 bytes)
```

## 3. Run the contract tests

A local EVM harness verifies SSTORE2 round-trips, hash verification, and the atomic
payment split — no network required:

```bash
node test/run-tests.js
# === 18 passed, 0 failed ===
```

## 4. Get testnet USDC

Go to **https://faucet.circle.com**, select **Arc Testnet**, paste your wallet address,
and request USDC. You'll need it to pay gas for deployment.

## 5. Deploy the registry to Arc

```bash
cp .env.example .env
# edit .env: set PRIVATE_KEY (and optionally FEE_RECIPIENT, FEE_BPS)

node scripts/deploy.js
# ✅ Deployed ArcInvoiceRegistry @ 0x....
# Wrote frontend config -> frontend/src/lib/deployment.json
```

The deploy script automatically writes the contract address + ABI into
`frontend/src/lib/deployment.json`, so the frontend is wired up with no manual step.

## 6. Serve the frontend

**Quick local run:**

```bash
PORT=8080 node backend/server.js
# open http://YOUR_PUBLIC_IP:8080
```

**Production (systemd + nginx):**

```bash
sudo mkdir -p /opt/attestra
sudo cp -r . /opt/attestra/
sudo cp deploy/attestra.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now attestra

sudo cp deploy/nginx-attestra.conf /etc/nginx/sites-available/attestra
sudo ln -s /etc/nginx/sites-available/attestra /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
# HTTPS: sudo certbot --nginx -d your-domain.com
```

> The server only serves static files. There is no upload endpoint by design — open
> `backend/server.js` and you'll see it can only read from `frontend/`.

---

## Using it

**Issue** (`/issue.html`): connect wallet → drop an invoice file → fill details →
optionally enable USDC payment → see the exact USDC cost → "Pay & seal on Arc" →
get a QR code + verify link.

**Verify** (`/verify.html?id=N`): opens from the QR. Reads the invoice from chain,
rebuilds the file, shows a green "Authentic" badge if the hash matches, renders a preview,
and — if payment is enabled — lets the customer pay in USDC.

**My invoices** (`/dashboard.html`): a profile view for the issuer. Connect a wallet and
it lists every invoice you've created (read from `InvoiceRegistered` events on Arc), with
live settlement status — *Awaiting payment*, *Partly paid*, *Paid*, or *No payment* — plus
totals for outstanding USDC. Copy a verify link or open any invoice in one click.

### Wallet connection (EIP-6963)

The dApp uses the **EIP-6963 multi-injected-provider** standard, so it discovers and lists
*every* wallet the visitor has installed (MetaMask, Rabby, Coinbase Wallet, Brave, OKX,
Trust, …) in a picker instead of fighting over a single `window.ethereum`. It also:

- silently reconnects the last wallet on the next visit,
- reacts live to account- and network-switches,
- auto-adds / switches to Arc Testnet and surfaces a "Wrong network" state, and
- offers an account menu (copy address, view on explorer, disconnect).

A legacy single-`window.ethereum` fallback is kept for older wallets.

### Self-contained frontend

`ethers`, `pako`, and `qrcodejs` are **vendored under `frontend/vendor/`** rather than
pulled from a CDN at runtime, so the dApp keeps working offline, behind strict CSP, or if a
CDN is down — important for a live demo. (Web fonts still load from Google Fonts as a
progressive enhancement and degrade gracefully to system fonts.)

---

## Revenue model (demo scope)

- **Registration markup** (primary): a markup on the on-chain storage/gas cost at seal time.
- **Payment fee** (`FEE_BPS`, default 2%): split atomically on every USDC payment.

On testnet, gas is free, so real revenue only begins at mainnet. Future ideas:
subscriptions for high-volume issuers, an API for accounting-system integration, and an
analytics dashboard. See `docs/GRANT.md`.

---

## Project layout

```
arc-invoice/
├── contracts/              Solidity (SSTORE2 + ArcInvoiceRegistry)
├── artifacts-solc/         pre-compiled ABI + bytecode
├── compile.js              standalone solc compiler (no network)
├── scripts/deploy.js       deploy to Arc, writes frontend config
├── test/                   local EVM test harness (18 tests)
├── frontend/               the dApp (static, talks straight to Arc)
│   ├── index.html          landing
│   ├── issue.html          issue + seal an invoice
│   ├── verify.html         verify + pay an invoice
│   ├── dashboard.html      "My invoices" issuer profile
│   ├── vendor/             self-hosted ethers / pako / qrcode (no CDN)
│   └── src/                core + per-page controllers
│       ├── lib/app.js      chain config, file processing, contract reads, issuer index
│       ├── lib/wallet-ui.js EIP-6963 wallet picker + header button + account menu
│       ├── issue.js / verify.js / dashboard.js
│       └── styles.css
├── backend/server.js       static server (no file storage)
├── deploy/                 systemd + nginx configs
└── docs/GRANT.md           grant pitch notes
```

## Security notes

- The platform fee is hard-capped at 5% in the contract (`MAX_FEE_BPS`).
- Payment uses checks-effects-interactions; `paid` is set before transfers.
- The verify/pay flow re-reads live on-chain state right before sending, so a stale UI
  can never submit the wrong amount or pay an already-settled invoice.
- The static server has a hardened path-traversal guard (resolved path must sit inside
  `frontend/` + separator) and serves only `frontend/`.
- Front-end libraries are vendored locally, removing a runtime third-party-CDN
  supply-chain dependency.
- Never commit `.env`. Keep your deployer key safe.
