const API_BASE = 'https://tapi.bale.ai/bot';

class BaleApiError extends Error {
  constructor(message, errorCode) {
    super(message);
    this.name = 'BaleApiError';
    this.errorCode = errorCode;
  }
}

// Every Bale bot API response looks like:
//   { ok: true,  result: ... }
//   { ok: false, error_code, description }
async function callApi(token, method, { params, file } = {}) {
  const url = `${API_BASE}${token}/${method}`;

  let response;
  if (file) {
    const form = new FormData();
    for (const [key, value] of Object.entries(params || {})) {
      if (value !== undefined && value !== null) form.append(key, String(value));
    }
    form.append(file.field, new Blob([file.buffer]), file.filename);
    response = await fetch(url, { method: 'POST', body: form });
  } else {
    response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(params || {}),
    });
  }

  let body;
  try {
    body = await response.json();
  } catch {
    throw new BaleApiError(`Invalid response from Bale API (HTTP ${response.status})`, response.status);
  }

  if (!body.ok) {
    const err = new BaleApiError(body.description || 'Unknown Bale API error', body.error_code);
    err.retryAfter = body.parameters && body.parameters.retry_after;
    throw err;
  }

  return body.result;
}

async function getMe(token) {
  return callApi(token, 'getMe');
}

async function sendMessage(token, chatId, text, { parseMode } = {}) {
  return callApi(token, 'sendMessage', {
    params: {
      chat_id: chatId,
      text,
      ...(parseMode ? { parse_mode: parseMode } : {}),
    },
  });
}

// `photo` is either { buffer, filename } (first send) or a Bale file_id string (subsequent sends)
async function sendPhoto(token, chatId, photo, { caption, parseMode } = {}) {
  const params = {
    chat_id: chatId,
    ...(caption ? { caption } : {}),
    ...(parseMode ? { parse_mode: parseMode } : {}),
  };
  if (typeof photo === 'string') {
    return callApi(token, 'sendPhoto', { params: { ...params, photo } });
  }
  return callApi(token, 'sendPhoto', { params, file: { field: 'photo', buffer: photo.buffer, filename: photo.filename } });
}

// `document` is either { buffer, filename } (first send) or a Bale file_id string (subsequent sends)
async function sendDocument(token, chatId, document, { caption, parseMode } = {}) {
  const params = {
    chat_id: chatId,
    ...(caption ? { caption } : {}),
    ...(parseMode ? { parse_mode: parseMode } : {}),
  };
  if (typeof document === 'string') {
    return callApi(token, 'sendDocument', { params: { ...params, document } });
  }
  return callApi(token, 'sendDocument', { params, file: { field: 'document', buffer: document.buffer, filename: document.filename } });
}

module.exports = { BaleApiError, getMe, sendMessage, sendPhoto, sendDocument };
