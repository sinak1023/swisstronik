'use strict';
// Thin wrapper around socket.io so bot + routes can emit without circular deps.
let io = null;

function setIo(instance) {
  io = instance;
}

// Rooms: `agent:<id>`, `admin`
function toAgent(agentId, event, payload) {
  if (io && agentId) io.to(`agent:${agentId}`).emit(event, payload);
}
function toAdmin(event, payload) {
  if (io) io.to('admin').emit(event, payload);
}
// Send to both the assigned agent and all admins
function toAgentAndAdmin(agentId, event, payload) {
  toAgent(agentId, event, payload);
  toAdmin(event, payload);
}

module.exports = { setIo, toAgent, toAdmin, toAgentAndAdmin, get io() { return io; } };
