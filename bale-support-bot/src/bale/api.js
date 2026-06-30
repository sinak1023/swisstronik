'use strict';
const fs = require('fs');
const config = require('../config');

const API_PREFIX = `${config.baleApiBase}/bot${config.baleToken}`;
const FILE_PREFIX = `${config.baleApiBase}/file/bot${config.baleToken}`;

// ---- Serialized queue with a minimum gap between calls (global throttle) ----
let chain = Promise.resolve();
let lastCall = 0;

function schedule(fn) {
  const run = async () => {
    const wait = Math.max(0, config.minIntervalMs - (Date.now() - lastCall));
    if (wait) await sleep(wait);
    lastCall = Date.now();
    return fn();
  };
  // queue sequentially, but don't let one rejection break the chain
  const result = chain.then(run, run);
  chain = result.then(() => {}, () => {});
  return result;
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

class BaleError extends Error {
  constructor(description, code, retryAfter) {
    super(description || 'Bale API error');
    this.name = 'BaleError';
    this.errorCode = code || 0;
    this.retryAfter = retryAfter || 0; // seconds
  }
}

async function rawRequest(method, body, isForm) {
  const url = `${API_PREFIX}/${method}`;
  const opts = { method: 'POST' };
  if (isForm) {
    opts.body = body; // FormData
  } else {
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(body || {});
  }
  let res;
  try {
    res = await fetch(url, opts);
  } catch (e) {
    throw new BaleError('Network error: ' + e.message, 0, 0);
  }
  let data;
  const txt = await res.text();
  try {
    data = txt ? JSON.parse(txt) : {};
  } catch {
    throw new BaleError('Invalid response from Bale: ' + txt.slice(0, 200), res.status, 0);
  }
  if (!res.ok || data.ok === false) {
    const retryAfter =
      (data.parameters && data.parameters.retry_after) ||
      (res.status === 429 ? parseInt(res.headers.get('retry-after') || '0', 10) : 0);
    throw new BaleError(data.description || `HTTP ${res.status}`, data.error_code || res.status, retryAfter);
  }
  return data.result;
}

// Public: JSON method call (throttled)
function call(method, params) {
  return schedule(() => rawRequest(method, params, false));
}

// Public: multipart call for uploading a local file (throttled)
function callForm(method, fields, fileField, file) {
  return schedule(async () => {
    const form = new FormData();
    for (const [k, v] of Object.entries(fields || {})) {
      if (v === undefined || v === null) continue;
      form.append(k, typeof v === 'object' ? JSON.stringify(v) : String(v));
    }
    if (file) {
      const buf = file.buffer || fs.readFileSync(file.path);
      const blob = new Blob([buf], { type: file.mime || 'application/octet-stream' });
      form.append(fileField, blob, file.name || 'file');
    }
    return rawRequest(method, form, true);
  });
}

// Resolve a Bale file_id to a downloadable URL
async function getFileUrl(fileId) {
  const f = await call('getFile', { file_id: fileId });
  if (!f || !f.file_path) return null;
  return `${FILE_PREFIX}/${f.file_path}`;
}

module.exports = { call, callForm, getFileUrl, BaleError, API_PREFIX, FILE_PREFIX };
