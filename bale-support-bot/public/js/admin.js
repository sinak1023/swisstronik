if (!Auth.requireRole('admin')) { /* redirected */ }

let socket = null;
let agentsCache = [];

(function init() {
  document.getElementById('me-name').textContent = Auth.user.username;
  setupNav();
  initDates();
  loadOverview();
  setupSocket();
  bindForms();
})();

const titles = { overview: 'داشبورد و آمار', agents: 'پشتیبان‌ها', surveys: 'نظرسنجی', satisfaction: 'رضایت/نارضایتی', settings: 'تنظیمات بات' };
function setupNav() {
  document.querySelectorAll('.nav-item[data-view]').forEach((el) =>
    el.addEventListener('click', () => switchView(el.dataset.view)));
  document.getElementById('logout').addEventListener('click', () => Auth.logout());
  const sb = document.getElementById('sidebar'), bd = document.getElementById('backdrop');
  document.getElementById('ham').addEventListener('click', () => { sb.classList.toggle('open'); bd.classList.toggle('show'); });
  bd.addEventListener('click', () => { sb.classList.remove('open'); bd.classList.remove('show'); });
}
function switchView(view) {
  document.querySelectorAll('.nav-item[data-view]').forEach((el) => el.classList.toggle('active', el.dataset.view === view));
  ['overview', 'agents', 'surveys', 'satisfaction', 'settings'].forEach((v) =>
    document.getElementById('view-' + v).classList.toggle('hidden', v !== view));
  document.getElementById('page-title').textContent = titles[view];
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('backdrop').classList.remove('show');
  if (view === 'overview') loadOverview();
  if (view === 'agents') loadAgents();
  if (view === 'surveys') loadSurveysView();
  if (view === 'satisfaction') loadSatisfaction();
  if (view === 'settings') loadSettings();
}

function initDates() {
  const today = new Date().toISOString().slice(0, 10);
  document.getElementById('ov-date').value = today;
  document.getElementById('st-to').value = today;
  document.getElementById('st-from').value = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
  document.getElementById('ov-preset').addEventListener('change', loadOverview);
  document.getElementById('ov-date').addEventListener('change', loadOverview);
}

// ---------------- overview ----------------
async function loadOverview() {
  const preset = document.getElementById('ov-preset').value;
  const day = document.getElementById('ov-date').value;
  const r = await apiGet(`/api/admin/overview?preset=${preset}&day=${day}`);
  agentsCache = r.agents;
  document.getElementById('ov-totals').innerHTML = [
    ['کل کاربران', r.totals.users, '#2f6df6'],
    ['پشتیبان‌ها', r.totals.agents, '#6a3df6'],
    ['پشتیبان فعال', r.totals.active_agents, '#1ea672'],
  ].map(([k, v, c]) => `<div class="card stat"><span class="k">${k}</span><span class="v" style="color:${c}">${v}</span></div>`).join('');

  document.getElementById('ov-agents').innerHTML = r.agents.map((a) => {
    const sv = a.latest_survey;
    const svText = sv && sv.votes ? `${sv.avg_rating ?? '-'} ⭐ (${sv.votes} رای)` : '—';
    return `<tr>
      <td><div style="display:flex;align-items:center;gap:8px"><img class="avatar sm" src="${a.photo_path ? avatarUrl(a.photo_path) : avatarPh()}"/>${esc(a.name)} ${a.active ? '' : '<span class="badge gray">غیرفعال</span>'}</div></td>
      <td>${a.share}</td><td>${a.totalUsers}</td><td>${a.received}</td><td>${a.read}</td>
      <td>${a.replied}</td><td><span class="badge ${a.pendingUsers ? 'red' : 'gray'}">${a.pendingUsers}</span></td>
      <td><span class="badge green">${a.satisfied}</span></td><td><span class="badge red">${a.dissatisfied}</span></td>
      <td>${svText}</td>
      <td><button class="btn sm ghost" onclick="viewAgentUsers(${a.id},'${esc(a.name)}')">کاربران</button></td>
    </tr>`;
  }).join('') || '<tr><td colspan="11" class="center-load">پشتیبانی تعریف نشده است</td></tr>';
}
function avatarPh() { return 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"><rect width="30" height="30" fill="%23dde4f0"/></svg>'); }

let _au = null;
function viewAgentUsers(agentId, name) {
  _au = { agentId, name, q: '', sat: '', unread: false, sort: 'recent', offset: 0, limit: 50 };
  openModal(`<h3>کاربران ${esc(name)}</h3>
    <div class="row" style="margin-bottom:8px">
      <input id="au-q" placeholder="🔍 نام / یوزرنیم / آیدی" autocomplete="off" style="min-width:150px"/>
      <select id="au-sat" class="shrink" style="max-width:150px"><option value="">وضعیت: همه</option><option value="satisfied">راضی امروز</option><option value="dissatisfied">ناراضی امروز</option></select>
      <select id="au-sort" class="shrink" style="max-width:150px"><option value="recent">جدیدترین</option><option value="oldest">قدیمی‌ترین</option><option value="messages">بیشترین پیام</option><option value="name">نام</option></select>
      <label class="shrink" style="display:flex;align-items:center;gap:4px;font-size:13px;white-space:nowrap"><input type="checkbox" id="au-unread" style="width:auto"/> فقط نخوانده</label>
    </div>
    <div id="au-count" style="font-size:12px;color:var(--muted);margin-bottom:6px"></div>
    <div class="table-wrap" style="max-height:50vh;overflow:auto"><table>
      <thead><tr><th>کاربر</th><th>دریافتی</th><th>پاسخ</th><th>نخوانده</th><th>عملیات</th></tr></thead>
      <tbody id="au-rows"></tbody></table></div>
    <div style="text-align:center;margin-top:10px"><button class="btn ghost hidden" id="au-more">نمایش بیشتر</button></div>
    <div class="modal-actions"><button class="btn ghost" onclick="closeModal()">بستن</button></div>`);
  const modal = document.querySelector('#modal-bg .modal'); if (modal) modal.style.maxWidth = '780px';
  let t;
  document.getElementById('au-q').addEventListener('input', (e) => { _au.q = e.target.value.trim(); clearTimeout(t); t = setTimeout(() => { _au.offset = 0; loadAgentUsers(false); }, 250); });
  document.getElementById('au-sat').addEventListener('change', (e) => { _au.sat = e.target.value; _au.offset = 0; loadAgentUsers(false); });
  document.getElementById('au-sort').addEventListener('change', (e) => { _au.sort = e.target.value; _au.offset = 0; loadAgentUsers(false); });
  document.getElementById('au-unread').addEventListener('change', (e) => { _au.unread = e.target.checked; _au.offset = 0; loadAgentUsers(false); });
  document.getElementById('au-more').addEventListener('click', () => { _au.offset += _au.limit; loadAgentUsers(true); });
  loadAgentUsers(false);
}
async function loadAgentUsers(append) {
  const rowsEl = document.getElementById('au-rows');
  if (!rowsEl) return;
  const p = new URLSearchParams({ q: _au.q, sort: _au.sort, limit: _au.limit, offset: _au.offset });
  if (_au.sat) p.set('sat', _au.sat);
  if (_au.unread) p.set('unread', '1');
  if (!append) rowsEl.innerHTML = '<tr><td colspan="5" class="center-load">در حال بارگذاری…</td></tr>';
  try {
    const r = await apiGet(`/api/admin/agents/${_au.agentId}/users?` + p.toString());
    const html = r.users.map(auRow).join('');
    if (append) rowsEl.insertAdjacentHTML('beforeend', html);
    else rowsEl.innerHTML = html || '<tr><td colspan="5" class="center-load">کاربری یافت نشد</td></tr>';
    const shownSoFar = _au.offset + r.shown;
    document.getElementById('au-count').textContent =
      `نمایش ${Math.min(shownSoFar, r.filtered)} از ${r.filtered}` + (r.filtered !== r.total ? ` (کل کاربران: ${r.total})` : '');
    document.getElementById('au-more').classList.toggle('hidden', shownSoFar >= r.filtered);
  } catch (e) { if (!append) rowsEl.innerHTML = '<tr><td colspan="5" class="center-load">خطا در بارگذاری</td></tr>'; }
}
function auRow(u) {
  const label = u.sat_today === 'satisfied' ? '<span class="badge green">راضی</span>'
    : u.sat_today === 'dissatisfied' ? '<span class="badge red">ناراضی</span>' : '';
  return `<tr>
    <td>${esc(userName(u))} ${label}</td>
    <td>${u.total_in}</td><td>${u.total_out}</td>
    <td>${u.unread ? `<span class="badge blue">${u.unread}</span>` : '0'}</td>
    <td><button class="btn sm ghost" onclick="readChat(${u.id})">خواندن چت</button>
        <button class="btn sm ghost" onclick="reassignUser(${u.id})">انتقال</button></td>
  </tr>`;
}

async function readChat(userId) {
  const r = await apiGet(`/api/admin/users/${userId}/messages`);
  const msgById = {};
  r.messages.forEach((m) => (msgById[m.id] = m));
  const html = r.messages.map((m) => {
    const cls = m.direction === 'in' ? 'in' : m.direction === 'system' ? 'system' : 'out';
    let body = m.type === 'text' ? esc(m.text).replace(/\n/g, '<br>') : renderAdminMedia(m);
    return `<div class="msg ${cls}" style="max-width:80%">${body}<div class="time">${fmtTime(m.created_at)}</div></div>`;
  }).join('');
  openModal(`<h3>گفتگو با ${esc(userName(r.user))}</h3>
    <div class="messages" style="height:55vh;border-radius:10px">${html || '<div class="center-load">پیامی نیست</div>'}</div>
    <div class="modal-actions"><button class="btn ghost" onclick="closeModal()">بستن</button></div>`);
}
function renderAdminMedia(m) {
  if (!m.file_id) return mediaLabelA(m.type);
  const url = mediaUrl(m.file_id);
  if (m.type === 'photo') return `<a href="${url}" target="_blank"><img src="${url}" style="max-width:200px;border-radius:8px"/></a>`;
  if (m.type === 'video') return `<video controls src="${url}" style="max-width:220px;border-radius:8px"></video>`;
  if (m.type === 'voice' || m.type === 'audio') return `<audio controls src="${url}"></audio>`;
  if (m.type === 'sticker') return `<img src="${url}" style="max-width:120px"/>`;
  return `<a href="${url}" target="_blank">📎 ${esc(m.file_name || 'فایل')}</a>`;
}
function mediaLabelA(t) { return { voice: '🎤 ویس', photo: '🖼 عکس', video: '🎬 ویدیو', audio: '🎵 صدا', document: '📎 فایل' }[t] || '📎'; }

async function reassignUser(userId) {
  const ags = await ensureAgents();
  const opts = ags.map((a) => `<option value="${a.id}">${esc(a.name)}</option>`).join('');
  openModal(`<h3>انتقال کاربر به پشتیبان دیگر</h3>
    <div class="field"><select id="reassign-agent">${opts}</select></div>
    <div class="modal-actions"><button class="btn" id="reassign-save">انتقال</button>
    <button class="btn ghost" onclick="closeModal()">انصراف</button></div>`);
  document.getElementById('reassign-save').addEventListener('click', async () => {
    await apiJson(`/api/admin/users/${userId}/reassign`, 'POST', { agent_id: Number(document.getElementById('reassign-agent').value) });
    closeModal(); toast('کاربر منتقل شد ✅'); loadOverview();
  });
}

// ---------------- agents ----------------
async function ensureAgents() {
  const r = await apiGet('/api/admin/agents');
  agentsCache = r.agents;
  return r.agents;
}
async function loadAgents() {
  const agents = await ensureAgents();
  document.getElementById('agents-rows').innerHTML = agents.map((a) => `<tr>
    <td><img class="avatar sm" src="${a.photo_path ? avatarUrl(a.photo_path) : avatarPh()}"/></td>
    <td>${esc(a.name)}</td><td>${esc(a.username)}</td><td>${esc((a.description || '').slice(0, 40))}</td>
    <td><input type="number" min="0" step="1" value="${a.share}" data-id="${a.id}" class="share-input" style="max-width:90px"/></td>
    <td>${a.active ? '<span class="badge green">فعال</span>' : '<span class="badge gray">غیرفعال</span>'}</td>
    <td>
      <button class="btn sm ghost" onclick="editAgent(${a.id})">ویرایش</button>
      <button class="btn sm danger" onclick="deleteAgent(${a.id})">حذف</button>
    </td></tr>`).join('') || '<tr><td colspan="7" class="center-load">پشتیبانی نیست</td></tr>';
}
document.getElementById('add-agent').addEventListener('click', () => agentModal(null));
document.getElementById('save-shares').addEventListener('click', async () => {
  const items = [...document.querySelectorAll('.share-input')].map((i) => ({ id: Number(i.dataset.id), share: Number(i.value) }));
  await apiJson('/api/admin/agents/shares', 'POST', { shares: items });
  toast('درصدها ذخیره شد ✅');
});

function agentModal(agent) {
  const a = agent || {};
  openModal(`<h3>${agent ? 'ویرایش' : 'افزودن'} پشتیبان</h3>
    <div class="field"><label>نام</label><input id="ag-name" value="${esc(a.name || '')}"/></div>
    <div class="field"><label>توضیحات</label><textarea id="ag-desc" rows="2">${esc(a.description || '')}</textarea></div>
    <div class="field"><label>نام کاربری (ورود)</label><input id="ag-username" value="${esc(a.username || '')}"/></div>
    <div class="field"><label>رمز عبور ${agent ? '(خالی = بدون تغییر)' : ''}</label><input id="ag-pass" type="text"/></div>
    <div class="field"><label>درصد سهم</label><input id="ag-share" type="number" min="0" value="${a.share != null ? a.share : 1}"/></div>
    <div class="field"><label>عکس پروفایل</label><input id="ag-photo" type="file" accept="image/*"/></div>
    <div class="field"><label>ویس خوش‌آمدگویی (هنگام تایید قوانین برای کاربر ارسال می‌شود)</label><input id="ag-voice" type="file" accept="audio/*"/></div>
    ${agent ? `<div class="field"><label>وضعیت</label><select id="ag-active"><option value="1" ${a.active ? 'selected' : ''}>فعال</option><option value="0" ${!a.active ? 'selected' : ''}>غیرفعال</option></select></div>` : ''}
    <div class="upload-bar hidden" id="ag-progress"><i class="fill"></i><span class="pct">0%</span></div>
    <div class="modal-actions"><button class="btn" id="ag-save">ذخیره</button>
    <button class="btn ghost" onclick="closeModal()">انصراف</button></div>`);
  document.getElementById('ag-save').addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('name', document.getElementById('ag-name').value);
    fd.append('description', document.getElementById('ag-desc').value);
    fd.append('username', document.getElementById('ag-username').value);
    const pass = document.getElementById('ag-pass').value;
    if (pass) fd.append('password', pass);
    fd.append('share', document.getElementById('ag-share').value);
    const photo = document.getElementById('ag-photo').files[0];
    const voice = document.getElementById('ag-voice').files[0];
    if (photo) fd.append('photo', photo);
    if (voice) fd.append('welcome_voice', voice);
    if (agent) fd.append('active', document.getElementById('ag-active').value);
    if (!agent && !pass) { toast('رمز عبور الزامی است', 'error'); return; }
    const saveBtn = document.getElementById('ag-save');
    const prog = document.getElementById('ag-progress');
    const fill = prog.querySelector('.fill');
    const pct = prog.querySelector('.pct');
    saveBtn.disabled = true; prog.classList.remove('hidden');
    const onP = (p) => { fill.style.width = p + '%'; pct.textContent = p + '%'; };
    try {
      const url = agent ? `/api/admin/agents/${agent.id}` : '/api/admin/agents';
      await xhrUpload(url, agent ? 'PUT' : 'POST', fd, onP);
      closeModal(); toast('ذخیره شد ✅'); loadAgents();
    } catch (e) { saveBtn.disabled = false; prog.classList.add('hidden'); handleApiError(e); }
  });
}
async function editAgent(id) {
  const a = agentsCache.find((x) => x.id === id) || (await ensureAgents()).find((x) => x.id === id);
  agentModal(a);
}
async function deleteAgent(id) {
  if (!confirm('حذف این پشتیبان؟ کاربران او بدون پشتیبان می‌شوند.')) return;
  await api(`/api/admin/agents/${id}`, { method: 'DELETE', body: '{}' });
  toast('حذف شد'); loadAgents();
}

// ---------------- surveys ----------------
async function loadSurveysView() {
  const agents = await ensureAgents();
  const opts = agents.map((a) => `<option value="${a.id}">${esc(a.name)}</option>`).join('');
  document.getElementById('sv-agent').innerHTML = opts;
  const fa = document.getElementById('sv-filter-agent');
  fa.innerHTML = opts;
  fa.onchange = () => loadSurveyResults(Number(fa.value));
  if (agents.length) loadSurveyResults(agents[0].id);
}
document.getElementById('sv-send').addEventListener('click', async () => {
  const agent_id = Number(document.getElementById('sv-agent').value);
  const period_weeks = Number(document.getElementById('sv-period').value);
  const question = document.getElementById('sv-question').value;
  try {
    const r = await apiJson('/api/admin/surveys', 'POST', { agent_id, period_weeks, question });
    toast(`نظرسنجی برای ${r.eligible} کاربر در حال ارسال است 📤`, 'ok', 4000);
    document.getElementById('sv-filter-agent').value = agent_id;
    setTimeout(() => loadSurveyResults(agent_id), 1500);
  } catch (e) { handleApiError(e); }
});
async function loadSurveyResults(agentId) {
  const r = await apiGet(`/api/admin/surveys?agent_id=${agentId}`);
  document.getElementById('sv-rows').innerHTML = r.surveys.map((s) => `<tr>
    <td>${fmtDateTime(s.created_at)}</td>
    <td>${s.period_weeks >= 5 ? '۴ هفته به بالا' : s.period_weeks + ' هفته'}</td>
    <td>${s.sent_count}/${s.target_count}</td>
    <td>${s.votes}</td>
    <td>${s.avg_rating != null ? s.avg_rating + ' ⭐' : '—'}</td>
    <td><span class="badge ${s.status === 'done' ? 'green' : 'blue'}">${s.status === 'done' ? 'پایان' : 'در حال ارسال'}</span></td>
    <td><button class="btn sm ghost" onclick="surveyResponses(${s.id})">جزئیات</button></td>
  </tr>`).join('') || '<tr><td colspan="7" class="center-load">نظرسنجی‌ای نیست</td></tr>';
}
async function surveyResponses(id) {
  const r = await apiGet(`/api/admin/surveys/${id}/responses`);
  const rows = r.responses.map((x) => `<tr><td>${esc(userName(x))}</td><td>${'⭐'.repeat(x.rating)} (${x.rating})</td><td>${fmtDateTime(x.created_at)}</td></tr>`).join('')
    || '<tr><td colspan="3" class="center-load">رایی ثبت نشده</td></tr>';
  openModal(`<h3>پاسخ‌های نظرسنجی</h3><div class="table-wrap"><table>
    <thead><tr><th>کاربر</th><th>امتیاز</th><th>تاریخ</th></tr></thead><tbody>${rows}</tbody></table></div>
    <div class="modal-actions"><button class="btn ghost" onclick="closeModal()">بستن</button></div>`);
}

// ---------------- satisfaction ----------------
async function loadSatisfaction() {
  const agents = await ensureAgents();
  const sel = document.getElementById('st-agent');
  if (sel.options.length <= 1) sel.innerHTML = '<option value="">همه پشتیبان‌ها</option>' + agents.map((a) => `<option value="${a.id}">${esc(a.name)}</option>`).join('');
  filterSatisfaction();
}
document.getElementById('st-load').addEventListener('click', filterSatisfaction);
async function filterSatisfaction() {
  const p = new URLSearchParams();
  const agent = document.getElementById('st-agent').value;
  const kind = document.getElementById('st-kind').value;
  const from = document.getElementById('st-from').value;
  const to = document.getElementById('st-to').value;
  const user = document.getElementById('st-user').value;
  if (agent) p.set('agent_id', agent);
  if (kind) p.set('kind', kind);
  if (from) p.set('from', from);
  if (to) p.set('to', to);
  if (user) p.set('user', user);
  const r = await apiGet('/api/admin/satisfaction?' + p.toString());
  document.getElementById('st-rows').innerHTML = r.items.map((x) => `<tr>
    <td>${esc(userName(x))}</td><td>${esc(x.agent_name)}</td>
    <td>${x.kind === 'satisfied' ? '<span class="badge green">راضی</span>' : '<span class="badge red">ناراضی</span>'}</td>
    <td>${esc(x.reason || '—')}</td><td>${x.day}</td></tr>`).join('')
    || '<tr><td colspan="5" class="center-load">موردی یافت نشد</td></tr>';
}

// ---------------- settings ----------------
async function loadSettings() {
  const r = await apiGet('/api/admin/settings');
  document.getElementById('set-rules').value = r.rules_text;
  document.getElementById('cur-voice').textContent = r.welcome_voice_path ? ('ویس فعلی: ' + r.welcome_voice_path) : 'ویسی تنظیم نشده است';
  document.getElementById('token-warn').innerHTML = r.bot_token_set ? '' :
    '<div class="toast warn" style="position:static;margin-bottom:12px">⚠️ توکن بات (BALE_BOT_TOKEN) در فایل .env تنظیم نشده است. بات فعال نخواهد بود.</div>';
}
document.getElementById('set-save').addEventListener('click', async () => {
  const fd = new FormData();
  fd.append('rules_text', document.getElementById('set-rules').value);
  const v = document.getElementById('set-voice').files[0];
  if (v) fd.append('welcome_voice', v);
  const pt = progressToast('ذخیره تنظیمات…');
  try {
    await xhrUpload('/api/admin/settings', 'POST', fd, (p) => pt.set(p));
    pt.done('تنظیمات ذخیره شد ✅'); loadSettings();
  } catch (e) { pt.done(); handleApiError(e); }
});

// ---------------- realtime ----------------
function setupSocket() {
  socket = connectSocket();
  // coalesce overview refreshes — under heavy message volume we must not reload on every event
  let ovTimer = null;
  const refreshOv = () => {
    if (document.getElementById('view-overview').classList.contains('hidden')) return;
    if (ovTimer) return;
    ovTimer = setTimeout(() => { ovTimer = null; loadOverview(); }, 4000);
  };
  socket.on('message:new', refreshOv);
  socket.on('satisfaction:update', refreshOv);
  socket.on('survey:progress', (d) => {
    if (!document.getElementById('view-surveys').classList.contains('hidden'))
      toast(`نظرسنجی: ${d.sent}/${d.target} ارسال شد`, 'ok', 1500);
  });
  socket.on('survey:done', () => { if (!document.getElementById('view-surveys').classList.contains('hidden')) { const fa = document.getElementById('sv-filter-agent'); if (fa.value) loadSurveyResults(Number(fa.value)); } });
  socket.on('user:new', refreshOv);
}
function bindForms() { /* reserved */ }
