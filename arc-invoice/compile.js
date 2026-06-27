// Standalone compiler using the npm `solc` package (no network needed).
// Compiles contracts/*.sol with optimizer + viaIR, writes artifacts to ./artifacts-solc.
const fs = require("fs");
const path = require("path");
const solc = require("solc");

const CONTRACTS_DIR = path.join(__dirname, "contracts");
const OUT_DIR = path.join(__dirname, "artifacts-solc");

function readSources() {
  const sources = {};
  for (const f of fs.readdirSync(CONTRACTS_DIR)) {
    if (f.endsWith(".sol")) {
      sources[f] = { content: fs.readFileSync(path.join(CONTRACTS_DIR, f), "utf8") };
    }
  }
  return sources;
}

// Resolve local imports like "./SSTORE2.sol"
function findImports(importPath) {
  const base = path.basename(importPath);
  const full = path.join(CONTRACTS_DIR, base);
  if (fs.existsSync(full)) return { contents: fs.readFileSync(full, "utf8") };
  return { error: "File not found: " + importPath };
}

const input = {
  language: "Solidity",
  sources: readSources(),
  settings: {
    optimizer: { enabled: true, runs: 200 },
    viaIR: true,
    outputSelection: {
      "*": { "*": ["abi", "evm.bytecode.object", "evm.deployedBytecode.object"] },
    },
  },
};

const output = JSON.parse(solc.compile(JSON.stringify(input), { import: findImports }));

let hasError = false;
if (output.errors) {
  for (const e of output.errors) {
    if (e.severity === "error") hasError = true;
    console.log(`[${e.severity}] ${e.formattedMessage}`);
  }
}
if (hasError) {
  console.error("\n❌ Compilation failed.");
  process.exit(1);
}

if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });
for (const file of Object.keys(output.contracts || {})) {
  for (const name of Object.keys(output.contracts[file])) {
    const c = output.contracts[file][name];
    fs.writeFileSync(
      path.join(OUT_DIR, `${name}.json`),
      JSON.stringify(
        { abi: c.abi, bytecode: "0x" + c.evm.bytecode.object },
        null,
        2
      )
    );
    const size = c.evm.deployedBytecode.object.length / 2;
    console.log(`✅ ${name}  (deployed size: ${size} bytes)`);
  }
}
console.log("\nArtifacts written to artifacts-solc/");
