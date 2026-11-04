#!/usr/bin/env python3
"""Authenticated GitHub push receiver with durable, coalesced deployment requests."""
import hashlib
import hmac
import json
import os
from pathlib import Path
import subprocess
import threading
import time
from http.server import BaseHTTPRequestHandler, HTTPServer

STATE = Path('/var/lib/aviato-deploy')
SECRET = Path('/etc/aviato-deploy/secret').read_bytes().strip()
LOCK = threading.Lock()

class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != '/hooks/github/deploy':
            return self.reply(404)
        try:
            size = int(self.headers.get('Content-Length', '0'))
            if not 0 < size <= 2_000_000:
                return self.reply(413)
            self.connection.settimeout(10)
            body = self.rfile.read(size)
            signature = 'sha256=' + hmac.new(SECRET, body, hashlib.sha256).hexdigest()
            if not hmac.compare_digest(signature, self.headers.get('X-Hub-Signature-256', '')):
                return self.reply(403)
            event = self.headers.get('X-GitHub-Event')
            data = json.loads(body)
            if event == 'ping':
                return self.reply(200)
            if event != 'push' or data.get('ref') != 'refs/heads/master' or data.get('deleted') or data.get('repository', {}).get('full_name') != 'AmirMehrabi/Aviato':
                return self.reply(204)
            revision = data.get('after', '')
            if len(revision) != 40 or any(c not in '0123456789abcdef' for c in revision):
                return self.reply(400)
            with LOCK:
                temp = STATE / 'pending.tmp'
                temp.write_text(revision)
                temp.replace(STATE / 'pending')
            return self.reply(202)
        except (ValueError, OSError):
            return self.reply(400)

    def reply(self, code):
        self.send_response(code)
        self.end_headers()


def worker():
    while True:
        try:
            with LOCK:
                pending = STATE / 'pending'
                revision = pending.read_text().strip() if pending.exists() else None
            if revision:
                completed = STATE / 'completed'
                if not completed.exists() or completed.read_text().strip() != revision:
                    result = subprocess.run(['/opt/aviato-deployer/ops/webhook/deploy', revision], timeout=1800)
                    if result.returncode:
                        print('Deployment failed; pending request retained for retry', flush=True)
                        time.sleep(300)
                        continue
                    completed.write_text(revision)
                with LOCK:
                    if pending.exists() and pending.read_text().strip() == revision:
                        pending.unlink()
        except Exception as error:
            print(type(error).__name__ + ': deployment worker failed', flush=True)
        time.sleep(5)

if __name__ == '__main__':
    threading.Thread(target=worker, daemon=True).start()
    HTTPServer(('127.0.0.1', 8791), Handler).serve_forever()
