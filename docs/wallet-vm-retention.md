# Wallet depletion and VM retention

## Admin configuration

- **Settings → account protection**: `wallet_retention_days`, default **7**, accepts 1–365 days. The existing debt percentage still determines when a running VM is shut down.
- **Billing → bundles**: optional `stopped_monthly_price` in the stored currency (IRR for this installation). Zero means free retention. An unset Proxmox price uses disk/IP rates capped at the bundle running rate. An unset Hetzner price keeps the provider rate.
- **Infrastructure locations**: an optional stopped price overrides the bundle price for that location.
- Customers with `auto_suspend_vms = false` retain the existing exemption from automatic wallet restrictions, including retention deletion.

## Lifecycle and customer experience

The restriction reconciliation job runs through the existing every-minute schedule. When effective balance (wallet less pending usage) is zero or negative, each existing VM receives an explicit depletion timestamp and deletion deadline. Existing indebted customers receive a full new window at first reconciliation; old VM creation/shutdown dates are not used to backdate deletion. Changing the admin setting does not change previously recorded deadlines.

Positive effective balance cancels the deadline. Partial funding that leaves the effective balance depleted does not extend it. An automatic delete that is queued can be cancelled before its worker starts; a started delete remains irreversible, including on retries. Previously running machines resume through the existing restriction recovery flow. Manually stopped machines remain stopped.

The customer panel shows Persian messages, Persian numerals, Jalali dates and Tehran time, with a top-up action. The funding suggestion covers debt, pending usage and a day of running resources across the owner's shared wallet, respecting the existing 250,000–50,000,000 toman payment limits. Larger debts explicitly require multiple payments. VM purchase and detail pages disclose stopped pricing and separate disk/backup charges.

In-app database notifications go to the owner and configured workspace wallet-alert recipients at depletion, debt shutdown, and within 48 and 24 hours of expiry. Delivery is deduplicated per VM, recipient and event. The existing balance SMS flow is unchanged; retention notices do not introduce email or SMS delivery.

At expiry, VM, addon disk and backup accrual is capped at the promised deadline even if cleanup is delayed. Automatic deletion disables backup policies, cancels queued backups, waits for active backups, removes retained backup files, then deletes the VM through the existing provider deletion job and releases its IP. Financial and activity records remain, and prior debt is not forgiven. Manually deleting a VM preserves separately billed backups under the existing policy; the confirmation screen explains this difference.

Accrual is recorded at confirmed power transitions so running and stopped periods use their respective rates. Daily settlement can still post a charge after expiry for consumption that occurred before the cutoff.

## Deployment and validation

Apply `2026_10_12_120000_add_stopped_pricing_and_wallet_retention.php`, build assets, and restart persistent queue workers. The scheduler and workers must continue processing the existing restriction and deletion queues. No new scheduled command is required.

Coverage: `tests/Feature/VmWalletRetentionTest.php` exercises pricing (including free retention, fallback and location/provider behavior), effective balance, preserved deadlines, funding cancellation, deduplicated reminders, cleanup, shared owner billing, admin settings and Persian customer pages.
