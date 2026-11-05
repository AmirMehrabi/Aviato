# Deployment SMS

Admin: `/settings/deployment-sms`. Restricted to administrators with SettingsManage permission. Recipient 09135512366 is prefilled and editable. Sending and every event start disabled. New templates must be approved before enabling. No existing provider templates are edited.

| Event | Template |
| --- | --- |
| Started (optional) | aviato-deploy-started-v1 |
| Success | aviato-deploy-success-v1 |
| Failure | aviato-deploy-failure-v1 |
| Confirmed rollback | aviato-deploy-rollback-v1 |
| Success after an earlier failure | aviato-deploy-recovery-v1 |

`%token` = short Git commit; `%token2` = active release number (before deployment for started, after completion/rollback for other events). For pre-release failures, the active release remains the previous release. No product version is invented. Template text is in `app/Services/Sms/DeploymentSmsTemplates.php`.

Run `php artisan deployment:configure-sms` to inspect provider approval and text equality. `--apply` submits missing templates only. It never updates an existing template or activates sending.

The master switch pauses queued sends; per-event switches, templates, editable recipient list, maximum attempts (1–10), explicit test sends, delivery history, manual retry and cancellation are available. Test sends use test values and go to saved recipients only. Retrying cancelled/failed messages requires valid current event settings; successful messages cannot be retried. Cancel cannot recall a message already accepted by the provider.

An independent Python/systemd worker reads private config and a SQLite outbox under `/var/www/html/aviato/shared/deploy-sms`. It survives broken Laravel/queues. PHP PDO SQLite must be installed for the admin history. Settings export the existing Kavenegar key to the private config file; saving settings refreshes this key after rotation. Never expose this file publicly. Folder mode 2770, group aviato-app; config/database mode 0660. Sender runs as deploy with UMask 0007.

`aviato-deploy-sms.service` starts `/opt/aviato-deployer/ops/webhook/notify.py worker`. Both webhook deployment and manual `composer deploy` report completion independently of Laravel. Success is queued only when the entire Deployer command returns successfully. Rollback is reported only when the active symlink matches Deployer's saved previous release. SMS failures never alter deployment status.

Events deduplicate by commit/event/recipient, so automatic retries do not repeatedly alert for the same failure. Recovery is a separate optional event and can accompany success. Failed requests retry with exponential backoff capped at an hour, stopping at the configured limit. Provider acceptance records its message ID; it does not prove handset delivery. A provider timeout after acceptance may produce a duplicate on retry.

Logs: `sudo journalctl -u aviato-deploy-sms -f`. The UI shows 100 latest sends; the outbox retains history. No real test SMS is sent as part of installation.
