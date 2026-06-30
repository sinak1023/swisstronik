// Shared front-end helpers
const Auth = {
  get token() { return localStorage.getItem('token'); },
  get user() { try { return JSON.parse(localStorage.getItem('user') || 'null'); } catch { return null; } },
  set(token, user) { localStorage.setItem('token', token); localStorage.setItem('user', JSON.stringify(user)); },
  clear() { localStorage.removeItem('token'); localStorage.removeItem('user'); },
  logout() { this.clear(); location.href = '/'; },
  requireRole(role) {
    const u = this.user;
    if (!this.token || !u) { location.href = '/'; return false; }
    if (role && u.role !== role) { location.href = u.role === 'admin' ? '/admin' : '/agent'; return false; }
    return true;
  },
};

async function api(path, opts = {}) {
  const headers = opts.headers || {};
  if (!(opts.body instanceof FormData)) headers['Content-Type'] = 'application/json';
  if (Auth.token) headers['Authorization'] = 'Bearer ' + Auth.token;
  const res = await fetch(path, { ...opts, headers });
  if (res.status === 401) { Auth.logout(); throw new Error('unauthorized'); }
  let data = null;
  try { data = await res.json(); } catch { data = null; }
  if (!res.ok) {
    const err = new Error((data && (data.error || data.description)) || ('خطا ' + res.status));
    err.status = res.status; err.data = data;
    throw err;
  }
  return data;
}

function apiGet(p) { return api(p); }
function apiJson(p, method, body) { return api(p, { method, body: JSON.stringify(body || {}) }); }
function apiForm(p, method, formData) { return api(p, { method, body: formData }); }

// ---- toast / popup ----
function ensureToastWrap() {
  let w = document.querySelector('.toast-wrap');
  if (!w) { w = document.createElement('div'); w.className = 'toast-wrap'; document.body.appendChild(w); }
  return w;
}
function toast(msg, kind = 'ok', ms = 3500) {
  const w = ensureToastWrap();
  const el = document.createElement('div');
  el.className = 'toast ' + kind;
  el.textContent = msg;
  w.appendChild(el);
  setTimeout(() => el.remove(), ms);
}

// handle send errors with rate-limit popup
function handleApiError(e) {
  if (e.status === 429 && e.data) {
    toast(`⛔ محدودیت ارسال بله! لطفاً ${e.data.retry_after} ثانیه صبر کنید و دوباره تلاش کنید.`, 'warn', 6000);
  } else {
    toast(e.message || 'خطایی رخ داد', 'error');
  }
}

// ---- utils ----
function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function fmtTime(ts) {
  const d = new Date(ts);
  return d.toLocaleString('fa-IR', { hour: '2-digit', minute: '2-digit' });
}
function fmtDateTime(ts) {
  const d = new Date(ts);
  return d.toLocaleString('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
function userName(u) {
  const n = [u.first_name, u.last_name].filter(Boolean).join(' ').trim();
  return n || u.username || ('کاربر ' + u.id);
}
function avatarUrl(path) { return path ? '/uploads/' + path : ''; }
function mediaUrl(fileId) { return '/api/media/' + fileId + '?token=' + encodeURIComponent(Auth.token); }

// socket.io connection (shared)
function connectSocket() {
  const s = io({ auth: { token: Auth.token } });
  return s;
}

// simple modal helper
function openModal(html) {
  let bg = document.getElementById('modal-bg');
  if (!bg) {
    bg = document.createElement('div'); bg.id = 'modal-bg'; bg.className = 'modal-bg';
    document.body.appendChild(bg);
    bg.addEventListener('click', (e) => { if (e.target === bg) closeModal(); });
  }
  bg.innerHTML = `<div class="modal">${html}</div>`;
  bg.classList.add('show');
  return bg;
}
function closeModal() {
  const bg = document.getElementById('modal-bg');
  if (bg) bg.classList.remove('show');
}
