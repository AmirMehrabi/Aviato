# Wallet SMS rollout

Production provider submissions on 2026-11-04 (all PendingReview at submission):

| Event | Template | Provider ID |
| --- | --- | --- |
| Existing low-credit thresholds, with remaining days | aviato-credit-days-v2 | 1556124 |
| Confirmed network isolation | aviato-credit-network-v2 | 1556125 |
| Confirmed wallet shutdown | aviato-credit-stopped-v2 | 1556126 |
| Confirmed restoration | aviato-credit-restored-v2 | 1556127 |

No existing provider template was edited. Active production settings were not changed. Code changes are staged locally and require the usual release process.

## Existing behavior

Three thresholds default to 15%, 10%, 5% of estimated monthly spending. Overrides, shared-wallet billing, deduplication, quiet hours, and SMS retries remain intact. Crossing several thresholds at once is coalesced into one alert. Existing in-app/email depletion and shutdown notices are retention notices, not SMS. Payment confirmation announces that services are being restored; it does not confirm restoration.

At effective balance <= 0, networking is isolated. Shutdown occurs at the configured negative percentage, not necessarily at zero. Lifecycle SMS is per VM and selected workspace recipients, once per restriction cycle, with queue retries. Pending messages for restrictions already cleared are skipped. Lifecycle SMS does not observe low-credit quiet hours. Existing customer SMS opt-outs and global gateway/SMS switch apply. Legacy VMs without a project do not receive the new lifecycle SMS.

## Activation after approval

1. Deploy the staged application code through the normal release process; restart queue workers.
2. Run `php artisan wallet:configure-sms` to inspect statuses and text equality. No provider templates are edited by this command. `--apply` creates missing templates only and saves returned IDs for later reads. Never activate a template marked TEXT DIFFERS.
3. In admin `/settings/protection`, set the four new template fields to the corresponding names above. Leave `aviato-negative-credit` in the existing field. The days field overrides it only when set; clearing that field restores the old SMS immediately. Clear lifecycle fields to disable those SMS messages.
4. `/settings/notifications` lists their configured status. The new fields remain blank by default and are never automatically enabled by submission or approval.

## Tokens and estimates

Low-credit: `%token` = customer's first name, `%token10` = workspace name, `%token20` = effective spendable balance in toman (integer with thousands separators), `%token2` = whole days remaining. Days = floor(max(0, effective balance) / monthly cost * 730 / 24). A partial day yields zero; the text explicitly explains that zero means less than a day. No recurring cost yields no estimate and uses the existing template instead. Estimates include all VMs, ready extra disks and backups paid by the owner's shared wallet and current running/stopped pricing. Currency conversion affects only the amount displayed, not the ratio.

Lifecycle: `%token` = customer first name; `%token10` = compact server name. Provider text is defined in `app/Services/Sms/WalletSmsTemplates.php`.

Creation and approval inspection do not send SMS. Do not use the corporate template updater for this rollout.
