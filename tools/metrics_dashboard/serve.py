"""Local, read-only metrics UI. Serves only allowlisted assets and aggregates."""

import argparse
import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit

from data import build_data
from diagnostics import saved_diagnostics

HERE = Path(__file__).resolve().parent
ASSETS = {"/": ("index.html", "text/html"), "/app.js": ("app.js", "text/javascript"),
          "/style.css": ("style.css", "text/css")}


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        path = urlsplit(self.path).path
        if path == "/api/diagnostics":
            self.reply(200, json.dumps(saved_diagnostics(), ensure_ascii=False).encode(), "application/json")
            return
        if path == "/api/data":
            try:
                body = json.dumps(build_data(), ensure_ascii=False).encode()
            except (OSError, ValueError, KeyError):
                self.reply(500, b'{"error":"Unable to read experiment summaries"}', "application/json")
                return
            self.reply(200, body, "application/json")
            return
        if path not in ASSETS:
            self.reply(404, b"Not found", "text/plain")
            return
        name, mime = ASSETS[path]
        self.reply(200, (HERE / name).read_bytes(), mime)

    def reply(self, status, body, mime):
        self.send_response(status)
        self.send_header("Content-Type", mime + "; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("Content-Security-Policy", "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'")
        self.end_headers()
        self.wfile.write(body)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, default=8787)
    args = parser.parse_args()
    server = ThreadingHTTPServer(("127.0.0.1", args.port), Handler)
    print(f"Metrics dashboard: http://127.0.0.1:{args.port}/", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
