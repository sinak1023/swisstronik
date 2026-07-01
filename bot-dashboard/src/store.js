const crypto = require('crypto');

// Simple in-memory stores. This app is meant to run as a single admin instance,
// so there is no need for a database - everything lives for the lifetime of the process.
const uploads = new Map(); // uploadId -> { columns, rows, sessionId }
const attachments = new Map(); // attachmentId -> { buffer, filename, mimetype, kind, sessionId }
const broadcasts = new Map(); // broadcastId -> { status, total, sent, failed, log[], sessionId }

const ONE_HOUR = 60 * 60 * 1000;

function makeId() {
  return crypto.randomBytes(12).toString('hex');
}

function put(map, data) {
  const id = makeId();
  map.set(id, { ...data, createdAt: Date.now() });
  return id;
}

// Periodically drop stale entries so memory doesn't grow unbounded on a long-running process.
setInterval(() => {
  const cutoff = Date.now() - ONE_HOUR;
  for (const map of [uploads, attachments, broadcasts]) {
    for (const [id, entry] of map) {
      if (entry.createdAt < cutoff) map.delete(id);
    }
  }
}, ONE_HOUR).unref();

module.exports = { uploads, attachments, broadcasts, put, makeId };
