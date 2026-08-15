<?php

namespace App\Services;

use App\Models\NetworkUsageBucket;
use App\Models\NetworkUsageRating;
use App\Models\UsageAccrual;
use App\Models\VirtualMachine;
use App\Models\VmNetworkBillingPeriod;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NetworkUsageRatingService
{
    public function __construct(private readonly NetworkBillingPolicyService $policies, private readonly WalletService $wallets) {}

    public function rate(NetworkUsageBucket $bucket): bool
    {
        if (! in_array($bucket->status, ['final', 'void'], true) || $bucket->completeness !== 'complete' || ! $bucket->virtual_machine_id) {
            return false;
        }

        return DB::transaction(function () use ($bucket): bool {
            $bucket = NetworkUsageBucket::query()->whereKey($bucket->id)->lockForUpdate()->firstOrFail();
            if (NetworkUsageRating::query()->where('network_usage_bucket_id', $bucket->id)->where('revision', $bucket->revision)->exists()) {
                return false;
            }

            $vm = VirtualMachine::query()->with(['bundle', 'customer', 'project.owner'])->findOrFail($bucket->virtual_machine_id);
            $policy = $this->policies->effective($vm);
            if (! $policy['enabled']) {
                $bucket->forceFill(['processing_status' => 'ignored', 'processing_error' => 'Network accounting is disabled.', 'rated_at' => now()])->save();

                return false;
            }

            $period = $this->policies->periodFor($vm, $bucket->interval_start);
            $period = VmNetworkBillingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($bucket->interval_end->greaterThan($period->period_end)) {
                $bucket->forceFill(['processing_status' => 'quarantined', 'processing_error' => 'Bucket crosses a billing month boundary.'])->save();

                return false;
            }

            $totals = NetworkUsageBucket::query()
                ->where('virtual_machine_id', $vm->id)
                ->where('status', 'final')
                ->where('completeness', 'complete')
                ->where('interval_start', '>=', $period->period_start)
                ->where('interval_end', '<=', $period->period_end)
                ->selectRaw('COALESCE(SUM(ingress_bytes), 0) as ingress, COALESCE(SUM(egress_bytes), 0) as egress')
                ->first();
            $ingress = (int) $totals->ingress;
            $egress = (int) $totals->egress;
            $ratedBytes = $this->policies->ratedBytes($period->direction, $ingress, $egress);
            $amount = $this->policies->amount($ratedBytes, $period->included_bytes, $period->price_per_unit, $period->price_unit_bytes);
            $delta = $amount - (int) $period->accrued_amount;

            $rating = NetworkUsageRating::query()->create([
                'network_usage_bucket_id' => $bucket->id,
                'vm_network_billing_period_id' => $period->id,
                'revision' => $bucket->revision,
                'ingress_bytes' => $bucket->status === 'void' ? 0 : $bucket->ingress_bytes,
                'egress_bytes' => $bucket->status === 'void' ? 0 : $bucket->egress_bytes,
                'rated_bytes' => $bucket->status === 'void' ? 0 : $this->policies->ratedBytes($period->direction, $bucket->ingress_bytes, $bucket->egress_bytes),
                'amount_delta' => $delta,
                'period_amount_after' => $amount,
                'policy_snapshot' => $period->policy_snapshot,
            ]);

            $this->applyFinancialDelta($vm, $period, $bucket, $rating, $delta);
            $period->forceFill([
                'ingress_bytes' => $ingress, 'egress_bytes' => $egress, 'rated_bytes' => $ratedBytes,
                'billable_bytes' => max(0, $ratedBytes - $period->included_bytes), 'accrued_amount' => $amount,
            ])->save();
            $bucket->forceFill(['processing_status' => 'rated', 'processing_error' => null, 'rated_at' => now()])->save();

            return true;
        });
    }

    private function applyFinancialDelta(VirtualMachine $vm, VmNetworkBillingPeriod $period, NetworkUsageBucket $bucket, NetworkUsageRating $rating, int $delta): void
    {
        if ($delta === 0) {
            return;
        }
        $customer = $vm->project?->owner ?? $vm->customer;
        if (! $customer) {
            throw new RuntimeException('VM has no billing customer.');
        }
        $scopeKey = $vm->project_id ? 'project:'.$vm->project_id : 'customer:'.$customer->id;
        $localDate = $bucket->interval_start->copy()->setTimezone($period->timezone)->toDateString();
        $accrual = UsageAccrual::query()->firstOrCreate([
            'customer_id' => $customer->id, 'scope_key' => $scopeKey,
            'category' => UsageAccrual::CATEGORY_NETWORK, 'resource_type' => 'network_usage_bucket',
            'resource_id' => $bucket->id, 'service_date' => $localDate,
        ], [
            'project_id' => $vm->project_id, 'virtual_machine_id' => $vm->id, 'resource_name' => $vm->name,
            'period_start' => $bucket->interval_start, 'period_end' => $bucket->interval_end,
            'amount' => 0, 'segments' => [], 'snapshot' => $period->policy_snapshot,
        ]);
        $accrual = UsageAccrual::query()->whereKey($accrual->id)->lockForUpdate()->firstOrFail();

        if (! $accrual->settled_at && (int) $accrual->amount + $delta >= 0) {
            $segments = $accrual->segments ?? [];
            $segments[] = ['bucket_id' => $bucket->bucket_id, 'revision' => $bucket->revision, 'amount' => $delta, 'rated_at' => now()->toIso8601String()];
            $accrual->forceFill(['amount' => (int) $accrual->amount + $delta, 'segments' => $segments, 'snapshot' => $period->policy_snapshot])->save();
            $rating->forceFill(['usage_accrual_id' => $accrual->id])->save();

            return;
        }

        // Historical settlements are immutable. Corrections become separately auditable wallet adjustments.
        $transaction = $delta > 0
            ? $this->wallets->charge($customer, $delta, 'اصلاح کارکرد شبکه', reference: $rating, metadata: ['category' => 'network_usage', 'correction' => true, 'bucket_id' => $bucket->bucket_id, 'revision' => $bucket->revision])
            : $this->wallets->refund($customer, abs($delta), 'بازپرداخت اصلاح کارکرد شبکه', reference: $rating, metadata: ['category' => 'network_usage', 'correction' => true, 'bucket_id' => $bucket->bucket_id, 'revision' => $bucket->revision]);
        $rating->forceFill(['wallet_transaction_id' => $transaction->id])->save();
    }
}
