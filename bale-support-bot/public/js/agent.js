if (!Auth.requireRole('agent')) { /* redirected */ }

let ME = null;
let socket = null;
let chats = [];
let activeUserId = null;
let msgById = {};
let replyTarget = null;

// ---------- init ----------
(async function init() {
  try {
    const r = await apiGet('/api/agent/me');
    ME = r.agent;
    document.getElementById('me-name').textContent = ME.name;
    const av = document.getElementById('me-avatar');
    if (ME.photo_path) av.src = avatarUrl(ME.photo_path);
  } catch (e) { /* */ }
  setupNav();
  setupComposer();
  await loadChats();
  setupSocket();
  initDates();
})();

// ---------- navigation ----------
const titles = { chats: 'گفتگوها', dashboard: 'داشبورد', stats: 'آمار من' };
function setupNav() {
  document.querySelectorAll('.nav-item[data-view]').forEach((el) => {
    el.addEventListener('click', () => switchView(el.dataset.view));
  });
  document.getElementById('logout').addEventListener('click', () => Auth.logout());
  const ham = document.getElementById('ham');
  const sb = document.getElementById('sidebar');
  const bd = document.getElementById('backdrop');
  ham.addEventListener('click', () => { sb.classList.toggle('open'); bd.classList.toggle('show'); });
  bd.addEventListener('click', () => { sb.classList.remove('open'); bd.classList.remove('show'); });
}
function switchView(view) {
  document.querySelectorAll('.nav-item[data-view]').forEach((el) =>
    el.classList.toggle('active', el.dataset.view === view));
  ['chats', 'dashboard', 'stats'].forEach((v) =>
    document.getElementById('view-' + v).classList.toggle('hidden', v !== view));
  document.getElementById('page-title').textContent = titles[view];
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('backdrop').classList.remove('show');
  if (view === 'dashboard') loadDashboard();
  if (view === 'stats') loadStats();
}

// ---------- chats ----------
async function loadChats() {
  const r = await apiGet('/api/agent/chats');
  chats = r.chats;
  renderChatList();
}
function renderChatList() {
  const q = (document.getElementById('chat-search').value || '').trim();
  const box = document.getElementById('chat-rows');
  const filtered = chats.filter((c) => !q || userName(c).includes(q) || String(c.id).includes(q));
  box.innerHTML = filtered.map((c) => {
    const label = c.sat_today === 'satisfied' ? '<span class="badge green">راضی</span>'
      : c.sat_today === 'dissatisfied' ? '<span class="badge red">ناراضی</span>' : '';
    const preview = c.last_type && c.last_type !== 'text' ? mediaLabel(c.last_type) : esc(c.last_text || '');
    return `<div class="chat-row ${c.id === activeUserId ? 'active' : ''}" data-id="${c.id}">
      <img class="avatar" src="${avatarPlaceholder(c)}" />
      <div class="meta">
        <div class="name">${esc(userName(c))} ${label}</div>
        <div class="last">${preview}</div>
      </div>
      ${c.unread ? `<span class="unread-dot">${c.unread}</span>` : ''}
    </div>`;
  }).join('') || '<div class="center-load">گفتگویی وجود ندارد</div>';
  box.querySelectorAll('.chat-row').forEach((el) =>
    el.addEventListener('click', () => openChat(Number(el.dataset.id))));
}
document.getElementById('chat-search').addEventListener('input', renderChatList);

function avatarPlaceholder() { return 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23dde4f0"/></svg>'); }
function mediaLabel(type) {
  return { voice: '🎤 پیام صوتی', photo: '🖼 عکس', video: '🎬 ویدیو', audio: '🎵 صدا', document: '📎 فایل', sticker: '🌟 استیکر' }[type] || '📎 فایل';
}

async function openChat(userId) {
  activeUserId = userId;
  document.getElementById('empty-chat').classList.add('hidden');
  document.getElementById('chat-active').classList.remove('hidden');
  // mobile: show main
  document.getElementById('chat-list').classList.add('hide-mobile');
  document.getElementById('chat-main').classList.remove('hide-mobile');
  renderChatList();

  const r = await apiGet(`/api/agent/chats/${userId}/messages`);
  msgById = {};
  r.messages.forEach((m) => (msgById[m.id] = m));
  const u = r.user;
  document.getElementById('peer-name').textContent = userName(u);
  document.getElementById('peer-sub').textContent = u.username ? '@' + u.username : ('ID: ' + u.id);
  document.getElementById('peer-avatar').src = avatarPlaceholder();
  renderMessages(r.messages);
  updateSatButtons(r.satisfaction_today);
  loadPins();
  // clear unread in list
  const c = chats.find((x) => x.id === userId);
  if (c) c.unread = 0;
  renderChatList();
}

function renderMessages(messages) {
  const box = document.getElementById('messages');
  if (!messages.length) {
    box.innerHTML = `<div class="empty-chat" style="flex:1">هنوز پیامی رد و بدل نشده است.<br>می‌توانید اولین پیام را ارسال کنید 👇</div>`;
    return;
  }
  box.innerHTML = messages.map(renderBubble).join('');
  attachBubbleTools();
  box.scrollTop = box.scrollHeight;
}
function renderBubble(m) {
  const cls = m.direction === 'in' ? 'in' : m.direction === 'system' ? 'system' : 'out';
  let body = '';
  if (m.type === 'text') body = esc(m.text).replace(/\n/g, '<br>');
  else body = renderMedia(m);
  let quote = '';
  if (m.reply_to_message_id && msgById[m.reply_to_message_id]) {
    const o = msgById[m.reply_to_message_id];
    quote = `<div class="reply-quote">${o.type === 'text' ? esc((o.text || '').slice(0, 80)) : mediaLabel(o.type)}</div>`;
  }
  const pin = m.pinned ? '<span class="pinned-flag">📌</span>' : '';
  const tools = m.direction !== 'system'
    ? `<div class="tools">
         <button data-act="reply" data-id="${m.id}">پاسخ</button>
         <button data-act="pin" data-id="${m.id}">${m.pinned ? 'برداشتن پین' : 'پین'}</button>
       </div>` : '';
  return `<div class="msg ${cls}" id="msg-${m.id}">${tools}${quote}${body}
    <div class="time">${pin} ${fmtTime(m.created_at)}</div></div>`;
}
function renderMedia(m) {
  const cap = m.text ? `<div style="margin-top:5px">${esc(m.text).replace(/\n/g, '<br>')}</div>` : '';
  if (!m.file_id) return mediaLabel(m.type) + ' (در دسترس نیست)' + cap;
  const url = mediaUrl(m.file_id);
  if (m.type === 'photo') return `<a href="${url}" target="_blank"><img class="media" src="${url}"/></a>${cap}`;
  if (m.type === 'video') return `<video class="media" controls src="${url}"></video>${cap}`;
  if (m.type === 'voice' || m.type === 'audio') return `<audio controls src="${url}"></audio>${cap}`;
  if (m.type === 'sticker') return `<img class="media" style="max-width:130px" src="${url}"/>${cap}`;
  return `<a class="file-chip" href="${url}" target="_blank">📎 ${esc(m.file_name || 'دانلود فایل')}</a>${cap}`;
}
function attachBubbleTools() {
  document.querySelectorAll('.msg .tools button').forEach((b) => {
    b.addEventListener('click', () => {
      const id = Number(b.dataset.id);
      if (b.dataset.act === 'reply') setReply(id);
      else togglePin(id);
    });
  });
}

// ---------- reply ----------
function setReply(id) {
  const m = msgById[id];
  if (!m) return;
  replyTarget = id;
  document.getElementById('reply-text').textContent = 'پاسخ به: ' + (m.type === 'text' ? (m.text || '').slice(0, 50) : mediaLabel(m.type));
  document.getElementById('reply-preview').classList.add('show');
  document.getElementById('composer-text').focus();
}
document.getElementById('reply-cancel').addEventListener('click', clearReply);
function clearReply() {
  replyTarget = null;
  document.getElementById('reply-preview').classList.remove('show');
}

// ---------- pins ----------
async function togglePin(id) {
  try {
    const r = await apiJson(`/api/agent/messages/${id}/pin`, 'POST', {});
    if (msgById[id]) msgById[id].pinned = r.pinned;
    const el = document.getElementById('msg-' + id);
    // re-render that bubble quickly
    if (el) el.outerHTML = renderBubble(msgById[id]);
    attachBubbleTools();
    loadPins();
    toast(r.pinned ? 'پیام پین شد 📌' : 'پین برداشته شد', 'ok', 1500);
  } catch (e) { handleApiError(e); }
}
async function loadPins() {
  try {
    const r = await apiGet(`/api/agent/chats/${activeUserId}/pins`);
    const bar = document.getElementById('pin-bar');
    if (!r.pins.length) { bar.classList.remove('show'); bar.innerHTML = ''; return; }
    bar.classList.add('show');
    bar.innerHTML = '<b>📌 پیام‌های پین‌شده:</b>' + r.pins.map((p) =>
      `<div class="pin-item" data-id="${p.id}">${p.type === 'text' ? esc((p.text || '').slice(0, 70)) : mediaLabel(p.type)}</div>`).join('');
    bar.querySelectorAll('.pin-item').forEach((el) => el.addEventListener('click', () => {
      const t = document.getElementById('msg-' + el.dataset.id);
      if (t) t.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));
  } catch (e) { /* */ }
}

// ---------- composer ----------
function setupComposer() {
  const ta = document.getElementById('composer-text');
  ta.addEventListener('input', () => { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 120) + 'px'; });
  ta.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendText(); }
  });
  document.getElementById('send-btn').addEventListener('click', sendText);
  document.getElementById('attach-btn').addEventListener('click', () => document.getElementById('file-input').click());
  document.getElementById('file-input').addEventListener('change', (e) => { sendMedia(e.target.files[0], ''); e.target.value = ''; });
  // voice recording (Telegram-like) — records OGG/Opus in the browser
  document.getElementById('voice-btn').addEventListener('click', startVoiceRecording);
  document.getElementById('rec-cancel').addEventListener('click', cancelRecording);
  document.getElementById('rec-send').addEventListener('click', sendRecording);
  // paste an image directly into the composer (like Telegram)
  ta.addEventListener('paste', (e) => {
    const item = [...(e.clipboardData?.items || [])].find((i) => i.type.startsWith('image/'));
    if (item) { const f = item.getAsFile(); if (f) { e.preventDefault(); sendMedia(f, 'photo'); } }
  });
  document.getElementById('btn-sat').addEventListener('click', () => satModal('satisfied'));
  document.getElementById('btn-dissat').addEventListener('click', () => satModal('dissatisfied'));
  document.getElementById('back-to-list').addEventListener('click', () => {
    document.getElementById('chat-list').classList.remove('hide-mobile');
    document.getElementById('chat-main').classList.add('hide-mobile');
  });
}
async function sendText() {
  const ta = document.getElementById('composer-text');
  const text = ta.value.trim();
  if (!text || !activeUserId) return;
  const btn = document.getElementById('send-btn');
  btn.disabled = true;
  try {
    const r = await apiJson(`/api/agent/chats/${activeUserId}/send`, 'POST', { text, replyDbId: replyTarget });
    appendMessage(r.message);
    ta.value = ''; ta.style.height = 'auto'; clearReply();
  } catch (e) { handleApiError(e); }
  finally { btn.disabled = false; }
}
async function sendMedia(file, kind) {
  if (!file || !activeUserId) return;
  const fd = new FormData();
  fd.append('file', file);
  if (kind) fd.append('kind', kind);
  if (replyTarget) fd.append('replyDbId', replyTarget);
  const labels = { voice: 'ارسال ویس', photo: 'ارسال عکس', video: 'ارسال ویدیو', audio: 'ارسال صدا' };
  const pt = progressToast((labels[kind] || 'ارسال فایل') + '…');
  try {
    const r = await xhrUpload(`/api/agent/chats/${activeUserId}/send-media`, 'POST', fd, (p) => pt.set(p));
    pt.done('ارسال شد ✅');
    appendMessage(r.message);
    clearReply();
  } catch (e) { pt.done(); handleApiError(e); }
}
function appendMessage(m) {
  msgById[m.id] = m;
  const box = document.getElementById('messages');
  const empty = box.querySelector('.empty-chat');
  if (empty) box.innerHTML = '';
  box.insertAdjacentHTML('beforeend', renderBubble(m));
  attachBubbleTools();
  box.scrollTop = box.scrollHeight;
}

// ---------- voice recorder (OGG/Opus in-browser, Bale-compatible) ----------
let recorder = null, recChunks = [], recTimer = null, recStart = 0;
function recBar(show) {
  document.getElementById('composer-normal').classList.toggle('hidden', show);
  document.getElementById('composer-recording').classList.toggle('hidden', !show);
}
async function startVoiceRecording() {
  if (!activeUserId) return;
  if (typeof Recorder === 'undefined' || (Recorder.isRecordingSupported && !Recorder.isRecordingSupported())) {
    return toast('ضبط ویس در این مرورگر پشتیبانی نمی‌شود', 'error');
  }
  try {
    recorder = new Recorder({
      encoderPath: '/vendor/encoderWorker.min.js',
      encoderApplication: 2048, // VOIP — optimized for speech
      encoderSampleRate: 48000,
      numberOfChannels: 1,
      streamPages: false,
    });
    recChunks = [];
    recorder.ondataavailable = (typed) => recChunks.push(typed);
    await recorder.start();
    recBar(true);
    recStart = Date.now();
    document.getElementById('rec-timer').textContent = '0:00';
    recTimer = setInterval(() => {
      const s = Math.floor((Date.now() - recStart) / 1000);
      document.getElementById('rec-timer').textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }, 250);
  } catch (e) {
    recorder = null;
    toast('دسترسی به میکروفون داده نشد', 'error');
  }
}
function endRecUI() { clearInterval(recTimer); recBar(false); }
async function cancelRecording() {
  if (recorder) {
    recorder.ondataavailable = () => {};
    try { await recorder.stop(); } catch {}
    try { recorder.close && recorder.close(); } catch {}
  }
  recorder = null; recChunks = [];
  endRecUI();
}
function sendRecording() {
  if (!recorder) return;
  const minMs = 600;
  if (Date.now() - recStart < minMs) return toast('ویس خیلی کوتاه است', 'warn', 1500);
  recorder.onstop = () => {
    const blob = new Blob(recChunks, { type: 'audio/ogg' });
    recorder = null; recChunks = [];
    if (blob.size > 0) sendMedia(new File([blob], 'voice.ogg', { type: 'audio/ogg' }), 'voice');
  };
  try { recorder.stop(); } catch { toast('خطا در ضبط ویس', 'error'); }
  endRecUI();
}

// ---------- satisfaction ----------
function satModal(kind) {
  if (!activeUserId) return;
  const label = kind === 'satisfied' ? 'ثبت رضایت' : 'ثبت نارضایتی';
  openModal(`<h3>${label} امروز</h3>
    <div class="field"><label>دلیل (فقط برای مدیر قابل مشاهده است)</label>
      <textarea id="sat-reason" rows="3" placeholder="اختیاری..."></textarea></div>
    <div class="modal-actions">
      <button class="btn ${kind === 'satisfied' ? 'green' : 'danger'}" id="sat-save">ثبت</button>
      <button class="btn ghost" onclick="closeModal()">انصراف</button>
    </div>`);
  document.getElementById('sat-save').addEventListener('click', async () => {
    try {
      await apiJson(`/api/agent/chats/${activeUserId}/satisfaction`, 'POST',
        { kind, reason: document.getElementById('sat-reason').value });
      closeModal();
      updateSatButtons({ kind });
      const c = chats.find((x) => x.id === activeUserId);
      if (c) { c.sat_today = kind; renderChatList(); }
      toast('ثبت شد ✅', 'ok', 1500);
    } catch (e) { handleApiError(e); }
  });
}
function updateSatButtons(sat) {
  const s = document.getElementById('btn-sat');
  const d = document.getElementById('btn-dissat');
  s.style.outline = sat && sat.kind === 'satisfied' ? '3px solid #0a7' : '';
  d.style.outline = sat && sat.kind === 'dissatisfied' ? '3px solid #b22' : '';
}

// ---------- realtime ----------
function setupSocket() {
  socket = connectSocket();
  socket.on('message:new', (m) => {
    if (m.agent_id !== ME.id) return;
    if (m.direction === 'system') return; // hide bot onboarding/system messages from the agent
    if (m.user_id === activeUserId) {
      if (!msgById[m.id]) appendMessage(m);
      if (m.direction === 'in') api(`/api/agent/chats/${activeUserId}/read`, { method: 'POST', body: '{}' }).catch(() => {});
    } else if (m.direction === 'in') {
      const c = chats.find((x) => x.id === m.user_id);
      if (c) { c.unread = (c.unread || 0) + 1; c.last_text = m.text; c.last_type = m.type; c.last_message_at = m.created_at; }
      else { loadChats(); return; }
      // move to top
      chats.sort((a, b) => (b.last_message_at || 0) - (a.last_message_at || 0));
      renderChatList();
      toast('پیام جدید از ' + userName(c), 'ok', 2000);
    }
  });
  socket.on('user:assigned', () => loadChats());
}

// ---------- dashboard ----------
function initDates() {
  const today = new Date().toISOString().slice(0, 10);
  document.getElementById('dash-date').value = today;
  document.getElementById('stat-to').value = today;
  const weekAgo = new Date(Date.now() - 6 * 86400000).toISOString().slice(0, 10);
  document.getElementById('stat-from').value = weekAgo;
  document.getElementById('dash-preset').addEventListener('change', loadDashboard);
  document.getElementById('dash-date').addEventListener('change', loadDashboard);
  document.getElementById('stat-load').addEventListener('click', loadStats);
}
async function loadDashboard() {
  const preset = document.getElementById('dash-preset').value;
  const day = document.getElementById('dash-date').value;
  const s = await apiGet(`/api/agent/stats?preset=${preset}&day=${day}`);
  const cards = [
    ['کاربران من', s.totalUsers, '#2f6df6'],
    ['پیام دریافتی', s.received, '#2f6df6'],
    ['خوانده‌شده', s.read, '#1ea672'],
    ['خوانده‌نشده', s.unread, '#e8a13a'],
    ['پاسخ‌داده', s.replied, '#1ea672'],
    ['در انتظار پاسخ', s.pendingUsers, '#e2483d'],
    ['کاربر فعال', s.activeUsers, '#6a3df6'],
    ['رضایت', s.satisfied, '#1ea672'],
    ['نارضایتی', s.dissatisfied, '#e2483d'],
  ];
  document.getElementById('dash-cards').innerHTML = cards.map(([k, v, c]) =>
    `<div class="card stat"><span class="k">${k}</span><span class="v" style="color:${c}">${v}</span></div>`).join('');
}
async function loadStats() {
  const from = document.getElementById('stat-from').value;
  const to = document.getElementById('stat-to').value;
  const r = await apiGet(`/api/agent/stats/series?from=${from}&to=${to}`);
  document.getElementById('stat-rows').innerHTML = r.series.map((d) =>
    `<tr><td>${d.day}</td><td>${d.received}</td><td>${d.read}</td><td>${d.replied}</td>
     <td>${d.satisfied}</td><td>${d.dissatisfied}</td></tr>`).join('') ||
    '<tr><td colspan="6" class="center-load">داده‌ای نیست</td></tr>';
}
