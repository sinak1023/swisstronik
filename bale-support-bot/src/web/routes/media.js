'use strict';
const express = require('express');
const { Readable } = require('stream');
const api = require('../../bale/api');
const { requireAuth } = require('../auth');
const router = express.Router();

// Proxy a Bale file_id to the browser (images/voice/video/docs in chat)
router.get('/:fileId', requireAuth(), async (req, res) => {
  try {
    const url = await api.getFileUrl(req.params.fileId);
    if (!url) return res.status(404).send('not found');
    const upstream = await fetch(url);
    if (!upstream.ok) return res.status(502).send('upstream error');
    const ct = upstream.headers.get('content-type');
    if (ct) res.setHeader('Content-Type', ct);
    res.setHeader('Cache-Control', 'private, max-age=86400');
    Readable.fromWeb(upstream.body).pipe(res);
  } catch (e) {
    res.status(500).send('error: ' + e.message);
  }
});

module.exports = router;
