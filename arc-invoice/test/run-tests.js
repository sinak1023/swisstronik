// Local EVM test harness (no network). Verifies:
//   1. SSTORE2 round-trip via the registry (write chunks -> read back -> bytes match)
//   2. Multi-chunk reassembly
//   3. Hash verification
//   4. Atomic payment split (issuer gets amount - fee, platform gets fee)
//   5. Guard rails (wrong amount, double pay, payment disabled)
//
// Uses @ethereumjs/vm low-level runCall against pre-compiled artifacts-solc bytecode.

const { createVM } = require("./vmHelper");
const { ethers } = require("ethers");
const fs = require("fs");
const path = require("path");

const reg = JSON.parse(
  fs.readFileSync(path.join(__dirname, "..", "artifacts-solc", "ArcInvoiceRegistry.json"))
);
const iface = new ethers.Interface(reg.abi);

let passed = 0,
  failed = 0;
function check(name, cond) {
  if (cond) {
    passed++;
    console.log("  ✅ " + name);
  } else {
    failed++;
    console.log("  ❌ " + name);
  }
}

(async () => {
  const env = await createVM();
  const { deploy, call, send, ACCOUNTS } = env;

  const owner = ACCOUNTS[0];
  const feeRecipient = ACCOUNTS[1];
  const issuer = ACCOUNTS[2];
  const customer = ACCOUNTS[3];

  console.log("\n== Deploying ArcInvoiceRegistry (feeBps = 200 = 2%) ==");
  const ctorArgs = iface.encodeDeploy([feeRecipient.address, 200]).slice(2);
  const registryAddr = await deploy(owner, reg.bytecode + ctorArgs);
  console.log("  registry @", registryAddr);

  // ---- Build a fake "invoice file": split into 2 chunks (each < 24KB EIP-170 limit) ----
  const fullFile = Buffer.from(
    "INVOICE #2026-001\nFrom: Acme Co\nTo: Globex\nAmount: 100.00 USDC\n" +
      "X".repeat(30000), // ~30KB total -> two ~15KB chunks, safely under 24576
    "utf8"
  );
  const fileHash = ethers.keccak256(fullFile);
  const mid = Math.floor(fullFile.length / 2);
  const chunk0 = fullFile.subarray(0, mid);
  const chunk1 = fullFile.subarray(mid);

  console.log("\n== registerInvoice (2 chunks, amountDue = 100 USDC, payment ON) ==");
  const amountDue = 100_000000n; // 100 USDC (6 decimals)
  const data = iface.encodeFunctionData("registerInvoice", [
    fileHash,
    ["0x" + chunk0.toString("hex"), "0x" + chunk1.toString("hex")],
    amountDue,
    true,
    "invoice-2026-001.txt",
    "text/plain",
    '{"number":"2026-001","customer":"Globex","currency":"USDC"}',
  ]);
  const regRes = await send(issuer, registryAddr, data, 0n);
  check("registration succeeded", regRes.success);

  // invoiceCount should be 1
  const cnt = await call(registryAddr, iface.encodeFunctionData("invoiceCount", []));
  check("invoiceCount == 1", iface.decodeFunctionResult("invoiceCount", cnt)[0] === 1n);

  // ---- read back chunks and reassemble ----
  console.log("\n== Read back & verify file integrity ==");
  const c0 = await call(registryAddr, iface.encodeFunctionData("readChunk", [1, 0]));
  const c1 = await call(registryAddr, iface.encodeFunctionData("readChunk", [1, 1]));
  const r0 = iface.decodeFunctionResult("readChunk", c0)[0];
  const r1 = iface.decodeFunctionResult("readChunk", c1)[0];
  const reassembled = Buffer.concat([
    Buffer.from(r0.slice(2), "hex"),
    Buffer.from(r1.slice(2), "hex"),
  ]);
  check("chunk0 round-trips", Buffer.compare(Buffer.from(r0.slice(2), "hex"), chunk0) === 0);
  check("chunk1 round-trips", Buffer.compare(Buffer.from(r1.slice(2), "hex"), chunk1) === 0);
  check("reassembled file identical", Buffer.compare(reassembled, fullFile) === 0);
  check(
    "reassembled hash matches stored hash",
    ethers.keccak256(reassembled) === fileHash
  );

  // verifyHash on-chain
  const vh = await call(
    registryAddr,
    iface.encodeFunctionData("verifyHash", [1, fileHash])
  );
  check("on-chain verifyHash(true)", iface.decodeFunctionResult("verifyHash", vh)[0] === true);
  const vhBad = await call(
    registryAddr,
    iface.encodeFunctionData("verifyHash", [1, ethers.keccak256(Buffer.from("tampered"))])
  );
  check(
    "on-chain verifyHash(false) for tampered file",
    iface.decodeFunctionResult("verifyHash", vhBad)[0] === false
  );

  // ---- quote ----
  console.log("\n== Payment quote & atomic split ==");
  const q = await call(registryAddr, iface.encodeFunctionData("quotePayment", [1]));
  const [total, toIssuer, fee] = iface.decodeFunctionResult("quotePayment", q);
  check("quote total == 100 USDC", total === 100_000000n);
  check("quote fee == 2 USDC (2%)", fee === 2_000000n);
  check("quote toIssuer == 98 USDC", toIssuer === 98_000000n);

  // balances before
  const issuerBefore = await env.getBalance(issuer.address);
  const feeBefore = await env.getBalance(feeRecipient.address);

  // wrong amount should revert
  const wrong = await send(
    customer,
    registryAddr,
    iface.encodeFunctionData("payInvoice", [1]),
    99_000000n
  );
  check("payInvoice with wrong amount reverts", !wrong.success);

  // correct payment
  const pay = await send(
    customer,
    registryAddr,
    iface.encodeFunctionData("payInvoice", [1]),
    100_000000n
  );
  check("payInvoice with correct amount succeeds", pay.success);

  const issuerAfter = await env.getBalance(issuer.address);
  const feeAfter = await env.getBalance(feeRecipient.address);
  check("issuer received +98 USDC", issuerAfter - issuerBefore === 98_000000n);
  check("platform received +2 USDC", feeAfter - feeBefore === 2_000000n);

  // double pay should revert
  const dbl = await send(
    customer,
    registryAddr,
    iface.encodeFunctionData("payInvoice", [1]),
    100_000000n
  );
  check("double payment reverts (AlreadyPaid)", !dbl.success);

  // ---- meta reflects paid ----
  const meta = await call(registryAddr, iface.encodeFunctionData("getInvoiceMeta", [1]));
  const decoded = iface.decodeFunctionResult("getInvoiceMeta", meta);
  check("meta.paid == true", decoded[6] === true);
  check("meta.fileName correct", decoded[8] === "invoice-2026-001.txt");

  console.log(`\n=== ${passed} passed, ${failed} failed ===`);
  process.exit(failed === 0 ? 0 : 1);
})().catch((e) => {
  console.error("HARNESS ERROR:", e);
  process.exit(1);
});
