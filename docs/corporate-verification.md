# Corporate identity verification

Customers keep their personal login identity. A separate company request is looked up with Zohal, submitted with authority documents, and reviewed by permitted administrators. Corporate status does not change VM quotas or prices.

## Installation and activation

1. Deploy the code and run `php artisan migrate`.
2. Run `php artisan billing:backfill-identities` before accepting conversions. This command is repeatable and snapshots available legacy identity details, not historical edits that were never recorded. It does not change amounts or wallet transactions. Pending payments and unfunded orders remain unsnapshotted until successful/funded.
3. Configure the existing Zohal national-code API token and Kavenegar API key in admin settings; enable Kavenegar as the SMS gateway. The company API uses the same Zohal token but requires company inquiry access.
4. Grant `corporate.read` and `corporate.review` to designated staff. Superadmins have both. Select notification reviewers at `/settings/corporate` on the admin portal.
5. Preview templates with `php artisan corporate:configure-sms`. Create/update them at Kavenegar with `php artisan corporate:configure-sms --apply`. The primary sending line defaults to **200000171**. The account must own/have access to that line. This command never sends an SMS.
6. To also change the primary line of existing configured login, ticket and wallet templates, use `php artisan corporate:configure-sms --apply --include-existing`. Their SMS text is preserved; these templates are set to SMS with automatic fallback disabled. Provider approval must be checked again after changes. Corporate templates use the exact text below; custom corporate template names can be saved in the settings page before running setup.
7. Confirm the templates are approved at Kavenegar, and confirm their primary line is `200000171`. Lookup's request API has no `sender` parameter; its line is configured on each template through `primaryLineNumber`.
8. Run the existing default queue worker, dedicated mail worker, and Laravel scheduler. Email deliveries use `EmailQueue::connection()` and the `mail` queue. Corporate notifications have a durable outbox and `corporate:recover-notifications` runs every five minutes.
9. Enable corporate requests at `/settings/corporate`. The feature defaults to disabled. Enable the desired channels; customer submission SMS is optional and defaults off.

Neither provider account settings nor live messages are changed by migrations or tests. External setup is explicit through `--apply`.

## Zohal API

Source: https://service.zohal.io/api/v0/services/openapi/?eng_title=company-inquiry

- POST `https://service.zohal.io/api/v0/services/inquiry/company_inquiry`
- Bearer token from the existing verification configuration.
- JSON input: `{ "national_id": "10123456789" }`.
- Require HTTP success, `result=1`, matching `response_body.data.national_id`, and a nonempty company name. Reject records with an activity end date for support review.
- Persist a whitelisted company result and lookup timestamp. A company lookup does not establish representative authority.
- Recheck lookups older than seven days during resubmission/approval. Changed registered details during review require correction and customer resubmission.

## Billing

Corporate approval schedules the company billing identity for midnight Asia/Tehran on the first day of the next Jalali month. All payments and domain orders use the same effective identity policy. Usage invoices resolve identity by service period, so generating an older invoice after approval does not change the billed party.

Invoices, successful payments and funded domain orders hold encrypted recipient snapshots. Approval also freezes any eligible unsnapshotted documents for that customer. The approved request stores conversion consent, wallet balance, company/representative details, reviewer and effective date. No wallet balance transfer or duplicate charge is created.

Hesabro's current adapter only documents individual customer fields. Corporate exports fail non-retryably with a clear accounting-review message until its legal-entity mapping is confirmed. Payment success and wallet credit remain intact. Existing individual exports use the recorded recipient snapshot.

## Notifications and recovery

- Submission/resubmission: selected active authorized reviewers receive email, SMS and in-app alerts; customer receives email/in-app confirmation and optional SMS.
- Correction, approval, rejection: customer receives enabled email/SMS/in-app alerts.
- Customer SMS master preference and email/in-app event preferences apply.
- Decisions and notifications are separate: delivery failure does not roll back a request or approval.
- Delivery rows are unique per request event, recipient and channel. Successful sends are skipped. In-app IDs are deterministic; the generic notification subscriber does not duplicate corporate email.
- SMS provider acceptance records a message ID; `sent` means accepted, not confirmed handset delivery.
- Connection timeouts, missing message IDs, and abandoned processing rows become `uncertain`. Review the provider's records before retrying. The request detail page exposes delivery states and a retry action; uncertain sends require explicit confirmation that they were not sent.
- Known failures retry up to three attempts. Scheduler recovery handles persisted rows when queue dispatch failed.

## Kavenegar Lookup templates

Official reference: https://kavenegar.com/rest.html

Create all templates as SMS templates, with primary sending line `200000171` and fallback disabled. `%token` is the numeric request number, e.g. `1042`. No company names, personal IDs or review reasons are included in SMS.

### AviatoCorpAdminReview

```text
آویاتو
درخواست احراز هویت حقوقی شماره %token آماده بررسی است.
لطفاً به پنل مدیریت مراجعه کنید.
```

### AviatoCorpSubmitted

```text
آویاتو
درخواست احراز هویت حقوقی شما به شماره %token ثبت شد.
نتیجه بررسی از طریق پنل اطلاع‌رسانی می‌شود.
```

### AviatoCorpCorrection

```text
آویاتو
درخواست احراز هویت حقوقی شماره %token نیازمند اصلاح است.
جزئیات را در بخش پروفایل پنل مشاهده کنید.
```

### AviatoCorpApproved

```text
آویاتو
درخواست احراز هویت حقوقی شماره %token تأیید شد.
تاریخ شروع صورتحساب حقوقی را در پروفایل پنل مشاهده کنید.
```

### AviatoCorpRejected

```text
آویاتو
درخواست احراز هویت حقوقی شماره %token رد شد.
دلیل و جزئیات را در بخش پروفایل پنل مشاهده کنید.
```
