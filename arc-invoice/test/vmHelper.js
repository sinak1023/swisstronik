// Minimal EVM harness over @ethereumjs/vm v8 low-level evm.runCall.
const { VM } = require("@ethereumjs/vm");
const { Common, Chain, Hardfork } = require("@ethereumjs/common");
const {
  Address,
  Account,
  hexToBytes,
  bytesToHex,
  privateToAddress,
} = require("@ethereumjs/util");

async function createVM() {
  const common = new Common({ chain: Chain.Mainnet, hardfork: Hardfork.Cancun });
  const vm = await VM.create({ common });
  const evm = vm.evm;

  // Deterministic test accounts, each funded with 1,000,000 * 1e6 "USDC-as-native".
  const privs = [
    "0x59c6995e998f97a5a0044966f0945389dc9e86dae88c7a8412f4603b6b78690d",
    "0x8b3a350cf5c34c9194ca85829a2df0ec3153be0318b5e2d3348e872092edffba",
    "0x7c852118294e51e653712a81e05800f419141751be58f605c371e15141b007a6",
    "0x47e179ec197488593b187f80a00eb0da91f1b9d0b13f8733639f19c30a34926a",
    "0x8166f546bab6da521a8369cab06c5d2b9e46670292d85c875ee9ec20e84ffb61",
  ];
  const ACCOUNTS = [];
  for (const pk of privs) {
    const addrBytes = privateToAddress(hexToBytes(pk));
    const address = new Address(addrBytes);
    const acct = new Account(0n, 1_000_000_000000n); // balance in base units
    await vm.stateManager.putAccount(address, acct);
    ACCOUNTS.push({ priv: pk, address: bytesToHex(addrBytes) });
  }

  async function getBalance(addrHex) {
    const acct = await vm.stateManager.getAccount(
      new Address(hexToBytes(addrHex))
    );
    return acct ? acct.balance : 0n;
  }

  // Deploy: run a create call, return deployed address (hex).
  async function deploy(from, bytecodeHex) {
    const res = await evm.runCall({
      caller: new Address(hexToBytes(from.address)),
      origin: new Address(hexToBytes(from.address)),
      data: hexToBytes(bytecodeHex.startsWith("0x") ? bytecodeHex : "0x" + bytecodeHex),
      gasLimit: 30_000_000n,
      value: 0n,
    });
    if (res.execResult.exceptionError) {
      throw new Error("deploy failed: " + res.execResult.exceptionError.error);
    }
    return bytesToHex(res.createdAddress.bytes);
  }

  // Static/read call: returns return data hex.
  async function call(toHex, dataHex) {
    const res = await evm.runCall({
      caller: new Address(hexToBytes(ACCOUNTS[0].address)),
      to: new Address(hexToBytes(toHex)),
      data: hexToBytes(dataHex),
      gasLimit: 30_000_000n,
      value: 0n,
    });
    if (res.execResult.exceptionError) {
      throw new Error("call reverted: " + res.execResult.exceptionError.error);
    }
    return bytesToHex(res.execResult.returnValue);
  }

  // State-changing call with value. Returns {success, returnValue}.
  async function send(from, toHex, dataHex, value) {
    const res = await evm.runCall({
      caller: new Address(hexToBytes(from.address)),
      origin: new Address(hexToBytes(from.address)),
      to: new Address(hexToBytes(toHex)),
      data: hexToBytes(dataHex),
      gasLimit: 30_000_000n,
      value: value,
    });
    return {
      success: !res.execResult.exceptionError,
      error: res.execResult.exceptionError
        ? res.execResult.exceptionError.error
        : null,
      returnValue: bytesToHex(res.execResult.returnValue),
    };
  }

  return { vm, deploy, call, send, getBalance, ACCOUNTS };
}

module.exports = { createVM };
