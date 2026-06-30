'use strict';
const db = require('../db');

// Pick the best agent for a NEW user using weighted-deficit distribution.
// Each active agent has a `share` (weight). Target load for an agent =
// share / sumShares * (totalAssigned + 1). We assign the new user to the agent
// with the largest positive deficit (target - current). With equal shares this
// yields equal distribution; with custom shares it respects the percentages.
function pickAgentForNewUser() {
  const agents = db.prepare('SELECT id, share FROM agents WHERE active = 1').all();
  if (agents.length === 0) return null;

  const counts = {};
  for (const a of agents) counts[a.id] = 0;
  const rows = db
    .prepare('SELECT agent_id, COUNT(*) c FROM users WHERE agent_id IS NOT NULL GROUP BY agent_id')
    .all();
  for (const r of rows) if (counts[r.agent_id] !== undefined) counts[r.agent_id] = r.c;

  const totalAssigned = Object.values(counts).reduce((s, n) => s + n, 0);
  const sumShares = agents.reduce((s, a) => s + (a.share > 0 ? a.share : 0), 0) || agents.length;

  let best = null;
  let bestDeficit = -Infinity;
  for (const a of agents) {
    const share = a.share > 0 ? a.share : 0;
    const target = (share / sumShares) * (totalAssigned + 1);
    const deficit = target - counts[a.id];
    if (deficit > bestDeficit) {
      bestDeficit = deficit;
      best = a.id;
    }
  }
  return best;
}

module.exports = { pickAgentForNewUser };
