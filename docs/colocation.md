# Private co-location services

Co-location is an opt-in, invoice-based service. It does not consume or credit the PAYG wallet. The workspace owner is the billing customer; only workspace members with the existing billing permission can view prices, order, pay, or request cancellation.

## Deployment

Run `php artisan migrate --force` as part of the normal deployment, before serving the updated application. The migration adds private plans, services, progress events, payable-invoice fields, and payment-to-invoice links. Unscheduled prepaid invoices keep their coverage dates empty until activation. It also widens invoice item unit-price precision to accommodate annual charges. Existing usage/domain billing remains unchanged. The migration is covered by the isolated SQLite test suite. Production rollout requires the normal deployment and migration steps.

Build assets with `npm run build`. Ensure the existing Laravel scheduler and email queue workers are running. `colocation:bill` runs daily at 09:00 Asia/Tehran and may also be run manually. It catches up missed renewal periods idempotently, sends one reminder before the due date and one after it, and marks services ended at their confirmed end date. It never disconnects power or networking.

## Admin setup and installation

1. Open **Billing tools → Co-location**, or **Customers → Customer → Co-location plans and services**.
2. Add the customer's negotiated plans. All admin monetary inputs are **toman**; amounts are stored as IRR. Monthly, quarterly, and yearly fields contain the **entire interval's price**, before the displayed tax. Leave an interval blank to hide it. At least one interval is required.
3. Specify U capacity, location, included power, connectivity, traffic policy, setup fee, tax, agreement terms, and delivery instructions. Mark only plans whose capacity you can fulfill as available.
4. Enable purchasing for that customer. Enabling requires an available plan and creates no service or charge. Disabling purchasing later preserves existing services and invoice access.
5. After the customer selects a plan, accepts the terms, and pays the first invoice, the order appears as **Paid — awaiting installation**. Track receipt, installation, and testing, with customer-visible equipment records and progress messages.
6. Complete installation and explicitly choose the billing start date. Dates entered in this form are Gregorian dates at midnight Tehran time; customer dates are displayed in Jalali. Today is the default. Backdating needs a reason and cannot precede the initial payment day. Future starts are supported. The chosen date, actor, and explanation are recorded.

The customer's first payment covers the full first interval, starting from the admin-selected date. For example, payment on November 1 with activation dated November 10 covers November 10–February 10 for a quarterly service. Paying alone does not start the billing clock or produce renewals. After activation the start date is locked; it cannot be silently changed through the portal.

## Customer experience

The portal shows only plans assigned to the workspace's billing customer. Checkout displays the actual period charge, setup fee, tax, initial total, traffic rules, delivery/start rule, and cancellation terms. The accepted plan and pricing are snapshotted on the service, so editing a plan changes future purchases only. If a plan changes during checkout, the customer must review it again.

Invoices can be paid directly through the configured **Mellat or Zibal** gateway. Hesabro's current integration funds an external wallet, so it is intentionally not offered for direct invoice payments. Existing callback verification is reused. Successful payments settle the linked invoice exactly once, create no wallet transaction or gift bonus, and return to the invoice. Duplicate pay clicks resume the pending attempt; a verified failed attempt can be retried.

The invoice includes a printable receipt after payment. Generic invoice/receipt links redirect co-location documents to this experience. Billing and service links remain accessible after disabling new purchases. A wallet debt or wallet lock does not prevent co-location invoice payment. Account suspension still restricts new orders and payment initiation.

## Recurring billing

- Space is prepaid monthly, quarterly, or yearly at the snapshotted price and tax rate; setup is charged once.
- Renewals are issued 14 days before their service period and are due at that period's start. The first invoice is due seven days after ordering.
- Periods use the **Gregorian anniversary in Asia/Tehran**, with Jalali presentation. They are calendar months, not fixed 30/90/365-day durations. Each boundary is calculated from the original start, so January 31 → February 28 → March 31 does not drift.
- Paying a later renewal before an earlier invoice does not advance the paid-through date past the unpaid gap.
- Invoice issue, service operation, and payment status are separate. Overdue invoices do not automatically suspend physical equipment or affect unrelated cloud services.
- Renewal is invoice-based; there is no automatic debit mandate.

## Traffic

Plans offer included monthly traffic plus additional usage, all-usage metering, no volumetric charge, or connectivity under a separate agreement. Metered policies use **outbound decimal GB**, free inbound, no rollover, and a monthly allowance reset on the service-start anniversary. Additional usage continues at the stated rate. Separate connectivity agreements can describe different policies without misrepresenting them as this meter.

For this release, traffic uses **admin-reviewed final measurements**, not automatic physical-port ingestion. In the active service, select a completed monthly period, enter its total outbound GB (up to three decimal places), identify the measurement source, and confirm finality. The system deducts the snapshotted allowance, applies the snapshotted rate and tax, and issues a separate invoice due in seven days. Annual/quarterly space payments do not change monthly traffic settlement. Each service/month has one settlement key; repeated submission cannot duplicate or overwrite the invoice. Within-allowance usage produces a zero-total paid record. Missing measurements never appear as confirmed zero usage.

## Cancellation and accounting boundaries

Customers request cancellation on the service page. This starts coordination and does not immediately disconnect equipment or stop the agreement. Admins can confirm an end at a paid rental-period boundary; future unpaid renewal invoices are voided, and no new periods start at or beyond that date. Paid future periods or pending gateway attempts must be resolved before confirming an earlier end. Physical collection is coordinated in the visible notes. History prevents deletion of the owning customer/workspace.

The release does not implement deposits, partial payments, bank-transfer verification, automated refunds/credit notes, automatic port metering, or modifications to an active service's price/quantity/interval. Early termination or invoice corrections need an explicit accounting review; do not edit issued amounts or silently change agreement snapshots. Co-location payments appear in the existing payment/invoice reports but are excluded from the **wallet-product** Hesabro export. A separate co-location accounting mapping is needed before automating that export.

## Validation

`php artisan test tests/Feature/ColocationTest.php` covers private access, checkout snapshots/idempotency, gateway retries/callbacks, wallet separation, admin installation/activation, recurring intervals and month boundaries, monthly traffic, cancellation, and reminders. Existing wallet, monthly-invoice, admin billing, and navigation tests provide regression coverage. Desktop and mobile browser review uses isolated fixture data, with no live gateway calls or customer data.
