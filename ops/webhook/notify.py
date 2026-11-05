#!/usr/bin/env python3
"""Deployment SMS outbox. No Laravel, PHP, or application queue dependency."""
import argparse
import json
import os
from pathlib import Path
import sqlite3
import time
import urllib.request
import urllib.parse

ROOT = Path(os.environ.get('AVIATO_SMS_STATE', '/var/www/html/aviato/shared/deploy-sms'))
EVENTS = ('started', 'success', 'failure', 'rollback', 'recovery')

class Connection(sqlite3.Connection):
    def __exit__(self, *args):
        try:
            return super().__exit__(*args)
        finally:
            self.close()


def database():
    connection = sqlite3.connect(ROOT / 'deliveries.sqlite', timeout=20, factory=Connection)
    connection.row_factory = sqlite3.Row
    connection.execute('''CREATE TABLE IF NOT EXISTS deliveries (
        id INTEGER PRIMARY KEY, event TEXT NOT NULL, revision TEXT NOT NULL, release TEXT NOT NULL,
        phone TEXT NOT NULL, template TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending',
        attempts INTEGER NOT NULL DEFAULT 0, next_attempt REAL NOT NULL DEFAULT 0,
        created_at REAL NOT NULL, sent_at REAL, provider_id TEXT, error TEXT,
        UNIQUE(event, revision, phone))''')
    connection.commit()
    return connection

def settings():
    return json.loads((ROOT / 'config.json').read_text())

def enqueue(event, revision, release):
    config = settings()
    rule = config.get('events', {}).get(event, {})
    if not config.get('enabled') or not rule.get('enabled') or not rule.get('template'):
        return
    with database() as db:
        for phone in config.get('recipients', []):
            db.execute('INSERT OR IGNORE INTO deliveries(event,revision,release,phone,template,created_at) VALUES(?,?,?,?,?,?)',
                       (event, revision, release, phone, rule['template'], time.time()))

def process_once():
    config = settings()
    if not config.get('enabled'):
        return
    with database() as db:
        # Single sender process. Admin actions serialize on this transaction.
        db.execute('BEGIN IMMEDIATE')
        row = db.execute("SELECT * FROM deliveries WHERE status='pending' AND next_attempt<=? ORDER BY id LIMIT 1", (time.time(),)).fetchone()
        if row is None:
            return
        rule = config.get('events', {}).get(row['event'], {})
        if row['phone'] not in config.get('recipients', []) or not rule.get('enabled') or rule.get('template') != row['template']:
            db.execute("UPDATE deliveries SET status='cancelled',error='Recipient, event or template changed' WHERE id=?", (row['id'],))
            return
        try:
            key = config.get('api_key', '')
            if not key:
                raise ValueError('Missing API key')
            payload = urllib.parse.urlencode({'receptor': row['phone'], 'template': row['template'],
                'token': row['revision'][:7], 'token2': row['release'] or 'unknown'}).encode()
            request = urllib.request.Request('https://api.kavenegar.com/v1/' + urllib.parse.quote(key, safe='') + '/verify/lookup.json', data=payload)
            with urllib.request.urlopen(request, timeout=15) as response:
                result = json.load(response)
            code = int(result.get('return', {}).get('status', 0))
            if code != 200:
                raise ValueError('Provider status ' + str(code))
            entries = result.get('entries') or [{}]
            db.execute("UPDATE deliveries SET status='sent',attempts=attempts+1,sent_at=?,provider_id=?,error=NULL WHERE id=?",
                       (time.time(), str(entries[0].get('messageid', '')), row['id']))
        except Exception as error:
            # Never persist exception URLs: Kavenegar embeds credentials in them.
            attempts = row['attempts'] + 1
            db.execute('UPDATE deliveries SET status=?,attempts=?,next_attempt=?,error=? WHERE id=?',
                ('failed' if attempts >= config.get('max_attempts', 5) else 'pending', attempts,
                 time.time() + min(3600, 60 * 2 ** (attempts - 1)), type(error).__name__ + ': delivery failed', row['id']))

def run():
    parser = argparse.ArgumentParser()
    parser.add_argument('event', choices=(*EVENTS, 'worker', 'init'))
    parser.add_argument('revision', nargs='?', default='manual')
    parser.add_argument('release', nargs='?', default='unknown')
    args = parser.parse_args()
    if args.event == 'init':
        database().close()
    elif args.event == 'worker':
        while True:
            try:
                process_once()
            except Exception as error:
                print(type(error).__name__ + ': SMS worker unavailable', flush=True)
            time.sleep(3)
    else:
        try:
            enqueue(args.event, args.revision, args.release)
        except Exception as error:
            print(type(error).__name__ + ': SMS enqueue failed', flush=True)
            # Notification failure must never change deployment exit status.

if __name__ == '__main__':
    run()
