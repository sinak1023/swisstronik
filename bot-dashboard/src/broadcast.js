const bale = require('./baleClient');
const { broadcasts } = require('./store');

const MAX_RETRIES = 3;
const CAPTION_LIMIT = 1024; // Bale/Telegram-style caption length limit for photo/document messages
const DEFAULT_RETRY_WAIT_MS = 2000;

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function withRetry(fn) {
  let attempt = 0;
  for (;;) {
    try {
      return await fn();
    } catch (err) {
      attempt += 1;
      const isRateLimit = err && err.errorCode === 429;
      if (!isRateLimit || attempt >= MAX_RETRIES) throw err;
      const waitMs = err.retryAfter ? err.retryAfter * 1000 : DEFAULT_RETRY_WAIT_MS;
      await sleep(waitMs);
    }
  }
}

async function sendToRecipient(token, chatId, { text, parseMode, attachment }) {
  const parseModeOpt = parseMode ? 'Markdown' : undefined;

  if (attachment) {
    const useCaption = text && text.length <= CAPTION_LIMIT;
    const send = attachment.kind === 'photo' ? bale.sendPhoto : bale.sendDocument;

    const fileArg = attachment.fileId || { buffer: attachment.buffer, filename: attachment.filename };
    const result = await withRetry(() =>
      send(token, chatId, fileArg, { caption: useCaption ? text : undefined, parseMode: useCaption ? parseModeOpt : undefined })
    );

    // Reuse Bale's file_id for every following recipient instead of re-uploading the same bytes.
    if (!attachment.fileId) {
      const file = attachment.kind === 'photo' ? result.photo && result.photo[result.photo.length - 1] : result.document;
      if (file && file.file_id) attachment.fileId = file.file_id;
    }

    if (text && !useCaption) {
      await withRetry(() => bale.sendMessage(token, chatId, text, { parseMode: parseModeOpt }));
    }
  } else if (text) {
    await withRetry(() => bale.sendMessage(token, chatId, text, { parseMode: parseModeOpt }));
  }
}

async function runBroadcast(io, broadcastId, { token, recipients, text, parseMode, attachment, delayMs }) {
  const state = broadcasts.get(broadcastId);
  state.status = 'running';
  state.total = recipients.length;

  const room = `broadcast:${broadcastId}`;

  for (const chatId of recipients) {
    if (state.status === 'cancelled') break;

    let entry;
    try {
      await sendToRecipient(token, chatId, { text, parseMode, attachment });
      state.sent += 1;
      entry = { chatId, status: 'success', at: Date.now() };
    } catch (err) {
      state.failed += 1;
      entry = { chatId, status: 'error', error: err.message || 'خطای نامشخص', at: Date.now() };
    }

    state.log.push(entry);
    io.to(room).emit('broadcast:progress', {
      broadcastId,
      sent: state.sent,
      failed: state.failed,
      total: state.total,
      entry,
    });

    if (delayMs > 0 && state.sent + state.failed < state.total) {
      await sleep(delayMs);
    }
  }

  state.status = state.status === 'cancelled' ? 'cancelled' : 'done';
  io.to(room).emit('broadcast:complete', {
    broadcastId,
    sent: state.sent,
    failed: state.failed,
    total: state.total,
    status: state.status,
  });
}

module.exports = { runBroadcast };
