# Monthly customer invoices

The scheduler issues the previous Jalali month's invoice at **00:15 Asia/Tehran on the first Jalali day of each month**. The daily cron expression is filtered by the Jalali calendar; Gregorian month starts do not trigger issuance. The normal Laravel scheduler must be running.

Periods start at Tehran midnight on the first day and end immediately before the next month's midnight. This handles 29-, 30-, and 31-day months, including leap-year Esfand. New invoice numbers use the Jalali service month, for example `INV-140507-0001`.

Compute, backup, and extra-disk lines summarize actual accrued usage at the configured hourly rates. Partial hours and service intervals crossing midnight are apportioned by the usage accrual service. Monthly issuance does not recalculate the configured hourly rates from the number of days in the Jalali month.

Each metered VM has a separate network line with received and sent GiB and its settled network charge. Final, complete, rated buckets within the invoice period supply the traffic totals. Usage within the included allowance appears as a zero-cost line. Unavailable metering is not represented as measured zero traffic. Existing network pricing, direction, and allowance-reset policies still determine charges; changing the invoice calendar does not change those policies.

Before issuing invoices, generation settles pending usage dates within the service month. Late metering for an already settled date creates a supplementary settlement; the original settlement and charge remain immutable. Only previously unsettled accruals are charged. Invoices summarize those charges and do not debit the wallet again. Re-running generation returns an existing invoice for the same customer and period.

Run `php artisan billing:generate-monthly-invoices` to generate the previous Jalali month's invoices manually. It can be run after the first day for recovery. Existing invoices are not regenerated, including when metering arrives after issuance. Before switching a deployment with existing Gregorian invoices, review its last invoiced service dates: archived accruals from earlier invoices are not reconstructed for the overlapping transition period.

No schema migration is required.
