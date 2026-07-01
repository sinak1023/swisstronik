(() => {
  const $ = (id) => document.getElementById(id);

  const state = {
    csv: null, // { uploadId, columns, totalRows, truncated, preview }
    selected: new Set(), // indices selected when not truncated
    attachment: null, // { attachmentId, kind, filename }
    broadcastId: null,
    socket: null,
  };

  function showError(elId, message) {
    const el = $(elId);
    el.textContent = message;
    el.style.display = message ? 'block' : 'none';
  }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {}),
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'خطای ناشناخته');
    return data;
  }

  async function postForm(url, formData) {
    const res = await fetch(url, { method: 'POST', body: formData });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'خطای ناشناخته');
    return data;
  }

  // ---------- Bot connect ----------

  $('connect-btn').addEventListener('click', async () => {
    showError('connect-error', '');
    const token = $('bot-token').value.trim();
    if (!token) return showError('connect-error', 'توکن را وارد کنید');
    $('connect-btn').disabled = true;
    try {
      const data = await postJson('/api/bot/connect', { token });
      $('connect-section').style.display = 'none';
      $('app-section').style.display = '';
      const status = $('bot-status');
      status.textContent = 'متصل به: @' + data.me.username;
      status.className = 'bot-status connected';
    } catch (err) {
      showError('connect-error', err.message);
    } finally {
      $('connect-btn').disabled = false;
    }
  });

  // ---------- CSV upload ----------

  $('csv-upload-btn').addEventListener('click', async () => {
    showError('csv-error', '');
    const file = $('csv-file').files[0];
    if (!file) return showError('csv-error', 'یک فایل CSV انتخاب کنید');
    const formData = new FormData();
    formData.append('file', file);
    $('csv-upload-btn').disabled = true;
    try {
      const data = await postForm('/api/upload/csv', formData);
      state.csv = data;
      state.selected = new Set(data.preview.map((_, i) => i));
      renderCsv();
    } catch (err) {
      showError('csv-error', err.message);
    } finally {
      $('csv-upload-btn').disabled = false;
    }
  });

  function renderCsv() {
    const { columns, preview, totalRows, truncated } = state.csv;

    const idColumnSelect = $('id-column');
    idColumnSelect.innerHTML = '';
    for (const col of columns) {
      const opt = document.createElement('option');
      opt.value = col;
      opt.textContent = col;
      idColumnSelect.appendChild(opt);
    }

    $('csv-meta').textContent = truncated
      ? `${totalRows} ردیف (فقط ${preview.length} ردیف اول قابل نمایش/انتخاب است؛ بقیه به‌طور خودکار شامل می‌شوند)`
      : `${totalRows} ردیف`;

    $('row-controls').style.display = truncated ? 'none' : 'flex';

    const thead = document.querySelector('#csv-table thead');
    const tbody = document.querySelector('#csv-table tbody');
    thead.innerHTML = '';
    tbody.innerHTML = '';

    const headRow = document.createElement('tr');
    if (!truncated) {
      const th = document.createElement('th');
      th.textContent = '✓';
      headRow.appendChild(th);
    }
    for (const col of columns) {
      const th = document.createElement('th');
      th.textContent = col;
      headRow.appendChild(th);
    }
    thead.appendChild(headRow);

    preview.forEach((row, i) => {
      const tr = document.createElement('tr');
      if (!truncated) {
        const td = document.createElement('td');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = state.selected.has(i);
        cb.addEventListener('change', () => {
          if (cb.checked) state.selected.add(i);
          else state.selected.delete(i);
          updateSelectedCount();
        });
        td.appendChild(cb);
        tr.appendChild(td);
      }
      for (const col of columns) {
        const td = document.createElement('td');
        td.textContent = row[col];
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    });

    $('csv-result').style.display = '';
    updateSelectedCount();
  }

  function updateSelectedCount() {
    if (!state.csv || state.csv.truncated) {
      $('selected-count').textContent = '';
      return;
    }
    $('selected-count').textContent = `${state.selected.size} از ${state.csv.preview.length} ردیف انتخاب شده`;
  }

  $('select-all-btn').addEventListener('click', () => {
    if (!state.csv) return;
    state.csv.preview.forEach((_, i) => state.selected.add(i));
    renderCsv();
  });

  $('select-none-btn').addEventListener('click', () => {
    state.selected.clear();
    renderCsv();
  });

  // ---------- Attachment upload ----------

  $('attachment-upload-btn').addEventListener('click', async () => {
    const file = $('attachment-file').files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    $('attachment-upload-btn').disabled = true;
    try {
      const data = await postForm('/api/upload/attachment', formData);
      state.attachment = data;
      $('attachment-status').textContent = `${data.filename} (${data.kind === 'photo' ? 'عکس' : 'فایل'}) آماده ارسال`;
      $('attachment-clear-btn').style.display = '';
    } catch (err) {
      showError('send-error', err.message);
    } finally {
      $('attachment-upload-btn').disabled = false;
    }
  });

  $('attachment-clear-btn').addEventListener('click', () => {
    state.attachment = null;
    $('attachment-file').value = '';
    $('attachment-status').textContent = '';
    $('attachment-clear-btn').style.display = 'none';
  });

  // ---------- Send / broadcast ----------

  $('send-btn').addEventListener('click', async () => {
    showError('send-error', '');
    const text = $('message-text').value;
    if (!text.trim() && !state.attachment) {
      return showError('send-error', 'متن پیام یا فایل ضمیمه را وارد کنید');
    }
    if (!state.csv) {
      return showError('send-error', 'ابتدا فایل CSV کاربران را آپلود کنید');
    }

    const idColumn = $('id-column').value;
    const selectedIndices = state.csv.truncated ? undefined : Array.from(state.selected);
    const delayMs = parseInt($('delay-ms').value, 10) || 0;

    $('send-btn').disabled = true;
    try {
      const data = await postJson('/api/broadcast/start', {
        uploadId: state.csv.uploadId,
        idColumn,
        selectedIndices,
        text,
        parseMode: $('parse-mode').checked,
        attachmentId: state.attachment ? state.attachment.attachmentId : undefined,
        delayMs,
      });
      startProgressTracking(data.broadcastId, data.total);
    } catch (err) {
      showError('send-error', err.message);
    } finally {
      $('send-btn').disabled = false;
    }
  });

  function startProgressTracking(broadcastId, total) {
    state.broadcastId = broadcastId;
    $('progress-section').style.display = '';
    $('live-log').innerHTML = '';
    updateProgressBar(0, 0, total);

    if (!state.socket) {
      state.socket = io();
      state.socket.on('broadcast:progress', onProgress);
      state.socket.on('broadcast:complete', onComplete);
    }
    state.socket.emit('broadcast:subscribe', broadcastId);
  }

  function onProgress(payload) {
    if (payload.broadcastId !== state.broadcastId) return;
    updateProgressBar(payload.sent, payload.failed, payload.total);
    appendLog(payload.entry);
  }

  function onComplete(payload) {
    if (payload.broadcastId !== state.broadcastId) return;
    updateProgressBar(payload.sent, payload.failed, payload.total);
    const summary = document.createElement('div');
    summary.className = 'log-line';
    summary.textContent = `پایان ارسال - وضعیت: ${payload.status} | موفق: ${payload.sent} | ناموفق: ${payload.failed}`;
    $('live-log').appendChild(summary);
    $('live-log').scrollTop = $('live-log').scrollHeight;
  }

  function updateProgressBar(sent, failed, total) {
    const done = sent + failed;
    const pct = total > 0 ? Math.round((done / total) * 100) : 0;
    $('progress-bar').style.width = pct + '%';
    $('progress-stats').textContent = `${done} از ${total} ارسال شد (موفق: ${sent}, ناموفق: ${failed}) - ${pct}%`;
  }

  function appendLog(entry) {
    const line = document.createElement('div');
    line.className = 'log-line ' + (entry.status === 'success' ? 'log-success' : 'log-error');
    const time = new Date(entry.at).toLocaleTimeString();
    line.textContent = entry.status === 'success'
      ? `[${time}] ✔ ${entry.chatId}`
      : `[${time}] ✘ ${entry.chatId} - ${entry.error}`;
    $('live-log').appendChild(line);
    $('live-log').scrollTop = $('live-log').scrollHeight;
  }

  $('cancel-btn').addEventListener('click', async () => {
    if (!state.broadcastId) return;
    try {
      await postJson(`/api/broadcast/${state.broadcastId}/cancel`, {});
    } catch (err) {
      // ignore
    }
  });
})();
