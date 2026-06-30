'use strict';

// Local day string YYYY-MM-DD from epoch ms (server local time)
function dayStr(ts = Date.now()) {
  const d = new Date(ts);
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

// Start-of-day epoch ms for a YYYY-MM-DD string (local)
function dayStartTs(day) {
  const [y, m, d] = day.split('-').map(Number);
  return new Date(y, m - 1, d, 0, 0, 0, 0).getTime();
}
function dayEndTs(day) {
  const [y, m, d] = day.split('-').map(Number);
  return new Date(y, m - 1, d, 23, 59, 59, 999).getTime();
}

function todayStr() {
  return dayStr(Date.now());
}

module.exports = { dayStr, dayStartTs, dayEndTs, todayStr };
