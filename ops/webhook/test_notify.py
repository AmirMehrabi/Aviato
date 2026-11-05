import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('notify', Path(__file__).with_name('notify.py'))
notify = importlib.util.module_from_spec(spec)
spec.loader.exec_module(notify)

class NotificationsTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        notify.ROOT = Path(self.temp.name)
        self.config = {'enabled': True, 'recipients': ['09135512366'], 'api_key':'fake', 'max_attempts': 2,
                       'events': {'success': {'enabled': True, 'template': 'approved'}}}
        self.save()

    def tearDown(self):
        self.temp.cleanup()

    def save(self):
        (notify.ROOT / 'config.json').write_text(json.dumps(self.config))

    def row(self):
        with notify.database() as db:
            return dict(db.execute('SELECT * FROM deliveries').fetchone())

    def test_event_deduplicates_and_disabled_does_not_enqueue(self):
        notify.enqueue('success', 'abcdef123', '147')
        notify.enqueue('success', 'abcdef123', '147')
        with notify.database() as db:
            self.assertEqual(1, db.execute('SELECT count(*) FROM deliveries').fetchone()[0])
        self.config['enabled'] = False
        self.save()
        notify.enqueue('success', 'other', '148')
        with notify.database() as db:
            self.assertEqual(1, db.execute('SELECT count(*) FROM deliveries').fetchone()[0])

    @patch.object(notify.urllib.request, 'urlopen')
    def test_master_switch_pauses_and_removed_recipient_cancels(self, send):
        notify.enqueue('success', 'abcdef123', '147')
        self.config['enabled'] = False
        self.save()
        notify.process_once()
        self.assertEqual('pending', self.row()['status'])
        self.config.update(enabled=True, recipients=[])
        self.save()
        notify.process_once()
        self.assertEqual('cancelled', self.row()['status'])
        send.assert_not_called()

    @patch.object(notify.urllib.request, 'urlopen', side_effect=RuntimeError('https://secret-key'))
    def test_delivery_failure_retries_and_redacts_credentials(self, send):
        notify.enqueue('success', 'abcdef123', '147')
        notify.process_once()
        self.assertEqual('pending', self.row()['status'])
        self.assertNotIn('secret', self.row()['error'])
        with notify.database() as db:
            db.execute('UPDATE deliveries SET next_attempt=0')
        notify.process_once()
        self.assertEqual('failed', self.row()['status'])
        self.assertEqual(2, self.row()['attempts'])

    @patch.object(notify.urllib.request, 'urlopen')
    def test_success_records_provider_id(self, send):
        import io
        send.return_value.__enter__.return_value = io.StringIO('{"return":{"status":200},"entries":[{"messageid":42}]}')
        notify.enqueue('success', 'abcdef123', '147')
        notify.process_once()
        self.assertEqual('sent', self.row()['status'])
        self.assertEqual('42', self.row()['provider_id'])

if __name__ == '__main__':
    unittest.main()
