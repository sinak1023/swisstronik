# Attestra — Grant pitch notes

## One line
On-chain invoice authenticity with atomic USDC settlement, built natively for Arc.

## The problem
Invoice fraud — forged, altered, and double-paid invoices — is a large, everyday loss for
businesses. Today, "proof" that an invoice is genuine relies on trusting a sender's email,
PDF, or a company server that can disappear or be tampered with.

## The solution
Attestra seals each invoice on Arc:
- The original file's **keccak256 fingerprint** is stored on-chain. Change one digit or
  pixel and verification fails — instantly and publicly.
- The **file itself** is stored on-chain via SSTORE2, so the invoice stays verifiable even
  if the issuer's company or server is gone.
- An optional **pay button** settles the invoice in native USDC, splitting the platform
  fee atomically in a single transaction. The platform never custodies funds.

## Why Arc specifically (not "any chain")
This is the part that matters for the grant — the design uses Arc's identity, not just its
EVM:

1. **USDC is native gas.** Payment uses `msg.value` directly — no ERC-20 approval dance.
   The whole flow (seal → verify → pay → settle) is denominated in USDC end-to-end.
2. **Dollar-denominated, predictable fees.** We can show the issuer the exact USDC cost
   *before* they seal. That UX is only honest on a chain with stable, dollar gas.
3. **Sub-second finality.** Verification and payment feel instant.
4. **Institutional/financial framing.** Arc targets payments, treasury, and settlement.
   Invoices are the most universal financial document there is — this lands squarely in
   that world, unlike a generic "files on chain" project.

It maps directly onto Arc's own highlighted use cases: **Peer-to-peer payments** and
**Treasury management**.

## What's novel vs. "store a file on a blockchain"
"Files on chain" is old. The novelty here is the **combination**:
- hybrid storage (cheap on-chain hash for instant verification **+** full file via SSTORE2
  for permanence), and
- **atomic settlement** of the invoice in the same system that proves its authenticity.

Proof-of-authenticity and payment usually live in separate systems. Attestra fuses them on
one chain, in USDC.

## Technical highlights (all implemented & tested)
- SSTORE2 chunked storage; each chunk < EIP-170 24,576-byte limit; multi-chunk streaming
  on read with a progress UI.
- Client-side gzip before storage to cut on-chain cost.
- Atomic fee split, hard-capped at 5% so the operator cannot abuse it.
- 18 passing tests on a local EVM (round-trip integrity, hash verify, payment split,
  guard rails: wrong amount, double pay, tampered file).
- Zero server-side file storage — privacy and trust-minimization by construction.

## Business model
- Primary: a markup on the on-chain registration/gas cost at seal time.
- Secondary: a small (default 2%) fee on USDC payments, split on-chain.
- Roadmap: subscriptions for high-volume issuers; API for accounting-system integration
  (QuickBooks/Xero-style); verification analytics dashboard; multi-currency via EURC.

## Honest limitations (say these up front — it builds credibility)
- On-chain storage cost grows with file size; we cap uploads (5 MB) and compress, but very
  large files are deliberately out of scope. The hybrid model means the *hash* check stays
  cheap regardless.
- Testnet gas is free, so revenue is a mainnet-era story; the testnet build is a working
  proof of the full flow.
- Public invoices by default. A privacy variant (encrypt file on-chain, share key via QR)
  is a natural follow-on that would lean on Arc's opt-in privacy.

## Demo script (for judges)
1. Issue: drop a sample invoice, enable payment, show the USDC cost estimate, seal it.
2. Show the QR / link; open the verify page.
3. Watch the file rebuild from chain with the green "Authentic" badge.
4. Edit one byte of the original file locally, re-hash, show it would fail verification.
5. Pay the invoice in USDC; show on arcscan that issuer + platform were paid in one tx.
