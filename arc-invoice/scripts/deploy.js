// Deploys ArcInvoiceRegistry to Arc Testnet using the locally-compiled artifact.
// Usage:
//   PRIVATE_KEY=0x... FEE_RECIPIENT=0x... FEE_BPS=200 node scripts/deploy.js
//
// Requires the deployer wallet to hold testnet USDC (gas). Get it from faucet.circle.com.

const { ethers } = require("ethers");
const fs = require("fs");
const path = require("path");
require("dotenv").config();

const RPC_URL = process.env.ARC_TESTNET_RPC_URL || "https://rpc.testnet.arc.network";
const PRIVATE_KEY = process.env.PRIVATE_KEY;
const FEE_RECIPIENT = process.env.FEE_RECIPIENT;
const FEE_BPS = parseInt(process.env.FEE_BPS || "200", 10); // default 2%

async function main() {
  if (!PRIVATE_KEY) throw new Error("Set PRIVATE_KEY in .env");
  const provider = new ethers.JsonRpcProvider(RPC_URL, { chainId: 5042002, name: "arc-testnet" });
  const wallet = new ethers.Wallet(PRIVATE_KEY, provider);
  const feeRecipient = FEE_RECIPIENT || wallet.address;

  console.log("Deployer     :", wallet.address);
  console.log("Fee recipient:", feeRecipient);
  console.log("Fee bps      :", FEE_BPS, `(${FEE_BPS / 100}%)`);

  const bal = await provider.getBalance(wallet.address);
  console.log("Balance      :", ethers.formatUnits(bal, 6), "USDC");
  if (bal === 0n) {
    console.warn("⚠️  Zero balance. Fund the deployer at https://faucet.circle.com (select Arc Testnet).");
  }

  const artifact = JSON.parse(
    fs.readFileSync(path.join(__dirname, "..", "artifacts-solc", "ArcInvoiceRegistry.json"))
  );

  const factory = new ethers.ContractFactory(artifact.abi, artifact.bytecode, wallet);
  console.log("\nDeploying ArcInvoiceRegistry...");
  const contract = await factory.deploy(feeRecipient, FEE_BPS);
  const tx = contract.deploymentTransaction();
  console.log("Tx hash      :", tx.hash);
  const receipt = await contract.deploymentTransaction().wait();
  await contract.waitForDeployment();
  const addr = await contract.getAddress();
  const deployBlock = receipt?.blockNumber ?? 0;

  console.log("\n✅ Deployed ArcInvoiceRegistry @", addr);
  console.log("Deploy block :", deployBlock);
  console.log("Explorer     : https://testnet.arcscan.app/address/" + addr);

  // Write address + ABI for the frontend to consume.
  const out = {
    chainId: 5042002,
    rpcUrl: RPC_URL,
    explorer: "https://testnet.arcscan.app",
    registry: addr,
    feeBps: FEE_BPS,
    feeRecipient,
    // Lets the dashboard scan InvoiceRegistered events from the right block.
    deployBlock,
    abi: artifact.abi,
  };
  const deployPath = path.join(__dirname, "..", "frontend", "src", "lib", "deployment.json");
  fs.writeFileSync(deployPath, JSON.stringify(out, null, 2));
  console.log("\nWrote frontend config -> frontend/src/lib/deployment.json");
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
