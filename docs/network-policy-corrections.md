# VM network billing overrides

Network policy fields on the VM take precedence over its bundle; null inherits
from the bundle, while zero prices/allowances and false enabled flags are explicit
overrides. Billing amounts use integer IRR and byte counts. A unit of 1073741824
bytes is one GiB, matching the usage display; prices are not multiplied by rounded
display values. Toman formatting divides the resulting IRR amount by ten.

The VM edit endpoint validates and persists network overrides. Changes to the
price, unit size, allowance or traffic direction reconcile the active open month,
including its previously rated usage. Rating new buckets also checks for policy
changes, so actual charges and the portal's stored period summary stay aligned.
Past periods retain their historical snapshots. Timezone changes take effect when
creating the next period, preserving existing bucket month boundaries.

Each reconciliation creates a `network_billing_policy_adjustments` audit record
with before/after policy and amounts. Reductions first reduce linked unsettled
accruals; any remainder is refunded. Increases create a pending adjustment accrual,
or a wallet charge when that service date was already settled. Settled accruals and
original bucket ratings are never rewritten. Period locks and the updated policy
make repeat reconciliation a no-op. Pricing permission is required to change cost
inputs through the admin endpoint.

To preview a correction (no writes):

```sh
php artisan network:refresh-vm-policy VM_UUID --price=6500 --unit-bytes=1073741824
```

Run the same command with `--apply` to save those explicit overrides and reconcile
the current period. Without price/unit options, it reconciles against the VM's
already saved effective policy. Back up the affected VM, periods, ratings and
accruals before applying a production repair. Avoid manually changing period totals
without their corresponding accrual or wallet adjustment.
