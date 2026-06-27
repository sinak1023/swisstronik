// server.js — tiny static server for the Attestra dApp.
//
// IMPORTANT: this server only serves the static frontend. It never receives,
// processes, or stores invoice files — all of that happens in the user's browser
// and goes straight to the Arc chain. There is intentionally no upload endpoint.
//
// Usage:  PORT=8080 node server.js
// Behind nginx/Caddy you can also just point the web root at ./frontend.

const http = require("http");
const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..", "frontend");
const PORT = process.env.PORT || 8080;
const HOST = process.env.HOST || "0.0.0.0";

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".mjs": "text/javascript; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".ico": "image/x-icon",
  ".woff2": "font/woff2",
  ".map": "application/json",
};

function safeJoin(base, target) {
  const p = path.normalize(path.join(base, target));
  // Path-traversal guard. Require the resolved path to be `base` itself or sit
  // strictly *inside* base + separator, so e.g. "/srv/frontend-evil" can't slip
  // past a naive startsWith("/srv/frontend") check.
  if (p !== base && !p.startsWith(base + path.sep)) return null;
  return p;
}

const server = http.createServer((req, res) => {
  try {
    let urlPath = decodeURIComponent(req.url.split("?")[0]);
    if (urlPath === "/") urlPath = "/index.html";
    let filePath = safeJoin(ROOT, urlPath);
    if (!filePath) {
      res.writeHead(400);
      res.end("Bad request");
      return;
    }
    if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
      // SPA-ish fallback: unknown path -> index.html
      filePath = path.join(ROOT, "index.html");
    }
    const ext = path.extname(filePath).toLowerCase();
    const type = MIME[ext] || "application/octet-stream";
    res.writeHead(200, {
      "Content-Type": type,
      "X-Content-Type-Options": "nosniff",
      "Referrer-Policy": "no-referrer",
      "Cache-Control": ext === ".html" ? "no-cache" : "public, max-age=3600",
    });
    fs.createReadStream(filePath).pipe(res);
  } catch (e) {
    res.writeHead(500);
    res.end("Server error");
  }
});

server.listen(PORT, HOST, () => {
  console.log(`Attestra static server running at http://${HOST}:${PORT}`);
  console.log(`Serving: ${ROOT}`);
  console.log("Note: no file storage. Files go browser -> Arc chain directly.");
});
