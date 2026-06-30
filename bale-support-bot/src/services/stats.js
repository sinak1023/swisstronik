'use strict';
const db = require('../db');
const { dayStartTs, dayEndTs, todayStr } = require('../util');

// Summary metrics for one agent within [fromTs, toTs]
function agentSummary(agentId, fromTs, toTs) {
  const received = db
    .prepare(
      `SELECT COUNT(*) c FROM messages WHERE agent_id=? AND direction='in' AND created_at BETWEEN ? AND ?`
    )
    .get(agentId, fromTs, toTs).c;
  const read = db
    .prepare(
      `SELECT COUNT(*) c FROM messages WHERE agent_id=? AND direction='in' AND read_by_agent=1 AND created_at BETWEEN ? AND ?`
    )
    .get(agentId, fromTs, toTs).c;
  const replied = db
    .prepare(
      `SELECT COUNT(*) c FROM messages WHERE agent_id=? AND direction='out' AND created_at BETWEEN ? AND ?`
    )
    .get(agentId, fromTs, toTs).c;
  const unread = received - read;

  // Users with a pending (unanswered) inbound message: last inbound newer than last outbound
  const pendingUsers = db
    .prepare(
      `SELECT COUNT(*) c FROM (
         SELECT u.id,
           (SELECT MAX(created_at) FROM messages m WHERE m.user_id=u.id AND m.direction='in') AS last_in,
           (SELECT MAX(created_at) FROM messages m WHERE m.user_id=u.id AND m.direction='out') AS last_out
         FROM users u WHERE u.agent_id=?
       ) t WHERE t.last_in IS NOT NULL AND (t.last_out IS NULL OR t.last_in > t.last_out)`
    )
    .get(agentId).c;

  const totalUsers = db.prepare('SELECT COUNT(*) c FROM users WHERE agent_id=?').get(agentId).c;

  const satisfied = db
    .prepare(
      `SELECT COUNT(*) c FROM satisfaction WHERE agent_id=? AND kind='satisfied' AND created_at BETWEEN ? AND ?`
    )
    .get(agentId, fromTs, toTs).c;
  const dissatisfied = db
    .prepare(
      `SELECT COUNT(*) c FROM satisfaction WHERE agent_id=? AND kind='dissatisfied' AND created_at BETWEEN ? AND ?`
    )
    .get(agentId, fromTs, toTs).c;

  // active users in range (sent at least one message)
  const activeUsers = db
    .prepare(
      `SELECT COUNT(DISTINCT user_id) c FROM messages WHERE agent_id=? AND direction='in' AND created_at BETWEEN ? AND ?`
    )
    .get(agentId, fromTs, toTs).c;

  return { received, read, unread, replied, pendingUsers, totalUsers, satisfied, dissatisfied, activeUsers };
}

// Per-day time series for an agent between two day strings (inclusive)
function agentDailySeries(agentId, fromDay, toDay) {
  const from = dayStartTs(fromDay);
  const to = dayEndTs(toDay);
  const rows = db
    .prepare(
      `SELECT date(created_at/1000,'unixepoch','localtime') AS day,
              SUM(CASE WHEN direction='in' THEN 1 ELSE 0 END) AS received,
              SUM(CASE WHEN direction='in' AND read_by_agent=1 THEN 1 ELSE 0 END) AS read,
              SUM(CASE WHEN direction='out' THEN 1 ELSE 0 END) AS replied
       FROM messages WHERE agent_id=? AND created_at BETWEEN ? AND ?
       GROUP BY day ORDER BY day`
    )
    .all(agentId, from, to);
  const sat = db
    .prepare(
      `SELECT day, SUM(CASE WHEN kind='satisfied' THEN 1 ELSE 0 END) satisfied,
              SUM(CASE WHEN kind='dissatisfied' THEN 1 ELSE 0 END) dissatisfied
       FROM satisfaction WHERE agent_id=? AND created_at BETWEEN ? AND ?
       GROUP BY day ORDER BY day`
    )
    .all(agentId, from, to);
  const satMap = {};
  for (const s of sat) satMap[s.day] = s;
  return rows.map((r) => ({
    ...r,
    satisfied: satMap[r.day] ? satMap[r.day].satisfied : 0,
    dissatisfied: satMap[r.day] ? satMap[r.day].dissatisfied : 0,
  }));
}

function rangeForPreset(preset, day) {
  const d = day || todayStr();
  if (preset === 'day') return { fromTs: dayStartTs(d), toTs: dayEndTs(d) };
  if (preset === 'week') {
    const end = dayEndTs(d);
    return { fromTs: end - 7 * 86400000 + 1, toTs: end };
  }
  if (preset === 'month') {
    const end = dayEndTs(d);
    return { fromTs: end - 30 * 86400000 + 1, toTs: end };
  }
  return { fromTs: dayStartTs(d), toTs: dayEndTs(d) };
}

// Survey aggregate for an agent (latest survey or all)
function agentSurveyStats(agentId) {
  const rows = db
    .prepare(
      `SELECT s.id, s.period_weeks, s.created_at, s.sent_count, s.target_count, s.status,
              COUNT(r.id) votes, AVG(r.rating) avg_rating
       FROM surveys s LEFT JOIN survey_responses r ON r.survey_id=s.id
       WHERE s.agent_id=? GROUP BY s.id ORDER BY s.created_at DESC`
    )
    .all(agentId);
  return rows.map((r) => ({ ...r, avg_rating: r.avg_rating ? Number(r.avg_rating.toFixed(2)) : null }));
}

module.exports = { agentSummary, agentDailySeries, rangeForPreset, agentSurveyStats };
