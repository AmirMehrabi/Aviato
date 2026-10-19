# Notifications and email templates

## Production setup

Deploy the code, run `php artisan migrate --force`, rebuild frontend assets, and restart queue workers (`php artisan queue:restart`, or `php artisan horizon:terminate` when using Horizon). The normal production deployment handles these steps. Keep the existing application key: stored SMTP credentials and queued notification jobs use it for encryption.

1. Open **Admin → Settings → Email**. Save the SMTP connection and sender identity, then send a test email.
2. Open **Admin → Settings → Templates and notifications** (`/settings/notifications`). Check the public logo URL, website address, and tagline. The default logo is Aviato's existing color PNG, served from `APP_URL`.
3. Expand an event to edit its subject and plain-text message, enable its channels, preview the saved email, or send a sample. Samples use demonstration values; `123456` in a sample is never a valid security code. Save before previewing or testing. Restore returns both the text and channel settings to the suggested defaults.
4. New optional email events start disabled. Enable the desired events individually. Ticket emails additionally require the existing **Ticket settings → Email notifications** switch. Security code emails are mandatory.
5. Customers can open **Profile → Notification settings** (`/profile/notifications`) to choose email and in-app delivery for optional customer events. These preferences apply across their workspaces. Admins must also enable the selected channel globally.

Available events: password recovery code, login code, email verification code, successful sign-in, password changed through recovery, workspace invitation, workspace wallet balance, VM retention/shutdown/deletion warning, server ready, payment received, node transfer completion (admin), and the existing ticket events for customers/support staff. Existing SMS delivery and SMS template configuration remain in the SMS/ticket settings.

Subjects and messages support the listed `{{variable}}` placeholders. User content is escaped in the shared branded email layout; saved templates are never executed as Blade or HTML. Authentication codes are inserted separately, so editing a message cannot accidentally remove the code. Each event shows its suggested copy as a reference. `{{body}}` preserves event-specific information such as retention deadlines and ticket details.

## Delivery behavior

All customer emails use persistent background queues and saved SMTP settings. Authentication emails use the dedicated `auth-mail` queue; optional emails use `mail`. Authentication jobs encrypt codes in queue storage, retry up to three attempts, and skip expired, replaced, or consumed codes. Optional event emails queue after commit, retry three times with increasing delays, and recheck recipient preferences and global event settings before delivery. Keep Horizon running with both dedicated email supervisors. `MAIL_QUEUE_CONNECTION` defaults to `redis`; synchronous and non-persistent queue drivers are rejected so SMTP cannot block customer requests. SMTP has an eight-second socket timeout and email jobs have a thirty-second worker timeout. Templates and SMTP changes are picked up without waiting for the worker to restart; restart workers after deploying code changes.

The admin template page shows queued, server-accepted, failed, and suppressed optional email deliveries, plus attempt counts. Server acceptance does not confirm inbox delivery. Authentication email delivery states are included; codes are never included. Sample emails are excluded from the history. Delivery records contain event, recipient, state, and timestamps, without codes, passwords, or raw transport errors. Confirmation messages say the email is queued, not delivered. Missing SMTP settings fail in the worker rather than falling back to a logging mailer. The usual SMTP retry ambiguity applies: a connection failure after server acceptance can cause a duplicate email.

## Test customer password recovery in production

1. Configure SMTP and send the connection test.
2. Sign out of the customer portal, open **Forgot password**, and enter the email address of your customer account (entering a phone number chooses SMS).
3. Open the branded recovery email and enter its six-digit code within ten minutes.
4. Choose a new password and sign in with it. The code is single use. A replacement code invalidates a previously verified recovery session.

Local automated tests cover the complete recovery flow using the generated code, code expiry after verification, replacement and reuse rejection, branded template rendering, editing/restoring, channel preferences, and queued delivery. No live customer email is sent by these tests.
