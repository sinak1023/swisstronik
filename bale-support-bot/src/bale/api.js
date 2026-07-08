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

// Public: JSON method call (throttled — used for sending, which Bale rate-limits)
function call(method, params) {
  return schedule(() => rawRequest(method, params, false));
}

// ---- Separate concurrency-limited pool for READ methods (getFile) ----
// Reads must NOT sit behind the send throttle, otherwise viewing media would
// serialize behind (and slow down) message delivery for everyone.
let readActive = 0;
const readQueue = [];
const READ_CONCURRENCY = 6;
function scheduleRead(fn) {
  return new Promise((resolve, reject) => {
    readQueue.push({ fn, resolve, reject });
    pumpRead();
  });
}
function pumpRead() {
  while (readActive < READ_CONCURRENCY && readQueue.length) {
    const { fn, resolve, reject } = readQueue.shift();
    readActive++;
    Promise.resolve().then(fn).then(resolve, reject).finally(() => { readActive--; pumpRead(); });
  }
}
function callRead(method, params) {
  return scheduleRead(() => rawRequest(method, params, false));
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

// Resolve a Bale file_id to a downloadable URL, cached (~50 min; Bale links live 1h).
// Cuts repeated getFile calls when many people view the same media.
const fileUrlCache = new Map(); // fileId -> { url, exp }
const FILE_URL_TTL = 50 * 60 * 1000;
async function getFileUrl(fileId) {
  const cached = fileUrlCache.get(fileId);
  if (cached && cached.exp > Date.now()) return cached.url;
  const f = await callRead('getFile', { file_id: fileId });
  if (!f || !f.file_path) return null;
  const url = `${FILE_PREFIX}/${f.file_path}`;
  fileUrlCache.set(fileId, { url, exp: Date.now() + FILE_URL_TTL });
  if (fileUrlCache.size > 5000) fileUrlCache.delete(fileUrlCache.keys().next().value);
  return url;
}

module.exports = { call, callRead, callForm, getFileUrl, BaleError, API_PREFIX, FILE_PREFIX };
