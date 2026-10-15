<?php

namespace App\Services;

use App\Models\NetworkBillingPolicyAdjustment;
use App\Models\NetworkUsageRating;
use App\Models\UsageAccrual;
use App\Models\UsageSettlement;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmNetworkBillingPeriod;
use Illuminate\Support\Facades\DB;

class NetworkBillingPolicyAdjustmentService
{
    public function __construct(private readonly NetworkBillingPolicyService $policies, private readonly WalletService $wallets) {}

    public function refresh(VirtualMachine $vm, ?User $actor = null): void
    {
        VmNetworkBillingPeriod::query()->where('virtual_machine_id', $vm->id)
            ->where('status', 'open')->where('period_start', '<=', now())->where('period_end', '>', now())
            ->each(fn ($period) => $this->synchronize($period, $actor));
    }

    public function needsRefresh(VmNetworkBillingPeriod $period, array $policy): bool
    {
        return $period->status === 'open' && now()->gte($period->period_start) && now()->lt($period->period_end)
            && ($period->price_per_unit !== $policy['price_per_unit']
                || $period->price_unit_bytes !== $policy['price_unit_bytes']
                || $period->included_bytes !== $policy['included_bytes']
                || $period->direction !== $policy['direction']);
    }

    public function synchronize(VmNetworkBillingPeriod $period, ?User $actor = null): VmNetworkBillingPeriod
    {
        return DB::transaction(function () use ($period, $actor): VmNetworkBillingPeriod {
            // Use the same VM -> period lock order as rating and admin updates.
            $vm = VirtualMachine::query()->with(['bundle', 'customer', 'project.owner'])
                ->whereKey($period->virtual_machine_id)->lockForUpdate()->firstOrFail();
            $period = VmNetworkBillingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($period->status !== 'open' || now()->lt($period->period_start) || now()->gte($period->period_end)) {
                return $period;
            }
            $policy = $this->policies->effective($vm);
            // A timezone change takes effect next month; do not move already rated buckets.
            $policy['timezone'] = $period->timezone;
            $policy['currency'] = $period->currency;
            $attributes = [
                'price_per_unit' => $policy['price_per_unit'], 'price_unit_bytes' => $policy['price_unit_bytes'],
                'included_bytes' => $policy['included_bytes'], 'direction' => $policy['direction'],
            ];
            $period->fill($attributes);
            if (! $period->isDirty(array_keys($attributes))) {
                return $period;
            }

            $rated = $this->policies->ratedBytes($policy['direction'], $period->ingress_bytes, $period->egress_bytes);
            $amount = $this->policies->amount($rated, $policy['included_bytes'], $policy['price_per_unit'], $policy['price_unit_bytes']);
            $delta = $amount - $period->accrued_amount;
            $adjustment = NetworkBillingPolicyAdjustment::create([
                'vm_network_billing_period_id' => $period->id, 'actor_user_id' => $actor?->id,
                'policy_before' => $period->policy_snapshot, 'policy_after' => $policy,
                'amount_before' => $period->accrued_amount, 'amount_after' => $amount, 'amount_delta' => $delta,
            ]);
            $this->adjustAccruals($vm, $period, $adjustment, $delta);
            $period->forceFill([
                'policy_snapshot' => $policy, 'rated_bytes' => $rated,
                'billable_bytes' => max(0, $rated - $policy['included_bytes']), 'accrued_amount' => $amount,
            ])->save();

            return $period;
        });
    }

    private function adjustAccruals(VirtualMachine $vm, VmNetworkBillingPeriod $period, NetworkBillingPolicyAdjustment $adjustment, int $delta): void
    {
        if ($delta === 0) {
            return;
        }
        $changes = [];
        if ($delta < 0) {
            $accruals = UsageAccrual::query()->whereNull('settled_at')
                ->where(function ($query) use ($period): void {
                    $query->whereIn('id', NetworkUsageRating::query()->select('usage_accrual_id')->where('vm_network_billing_period_id', $period->id))
                        ->orWhereIn('id', NetworkBillingPolicyAdjustment::query()->select('usage_accrual_id')->where('vm_network_billing_period_id', $period->id));
                })->orderBy('id')->lockForUpdate()->get();
            foreach ($accruals as $accrual) {
                $reduction = min(-$delta, $accrual->amount);
                if ($reduction === 0) {
                    continue;
                }
                $segments = $accrual->segments ?? [];
                $segments[] = ['policy_adjustment_id' => $adjustment->id, 'amount' => -$reduction, 'rated_at' => now()->toIso8601String()];
                $accrual->forceFill([
                    'amount' => $accrual->amount - $reduction, 'segments' => $segments,
                    'snapshot' => $adjustment->policy_after,
                ])->save();
                $changes[] = ['usage_accrual_id' => $accrual->id, 'amount_delta' => -$reduction];
                $delta += $reduction;
                if ($delta === 0) {
                    break;
                }
            }
        }
        $customer = $period->customer;
        $scopeKey = $period->project_id ? 'project:'.$period->project_id : 'customer:'.$customer->id;
        $serviceDate = now()->setTimezone($period->timezone)->toDateString();
        $alreadySettled = UsageSettlement::query()->where('customer_id', $customer->id)->where('scope_key', $scopeKey)
            ->whereDate('service_date', $serviceDate)->whereNotNull('settled_at')->exists();
        if ($delta > 0 && ! $alreadySettled) {
            $accrual = UsageAccrual::create([
                'customer_id' => $customer->id, 'project_id' => $period->project_id, 'scope_key' => $scopeKey,
                'category' => UsageAccrual::CATEGORY_NETWORK, 'resource_type' => 'network_policy_adjustment',
                'resource_id' => $adjustment->id, 'virtual_machine_id' => $vm->id, 'resource_name' => $vm->name,
                'service_date' => $serviceDate, 'period_start' => $period->period_start, 'period_end' => now(),
                'amount' => $delta, 'snapshot' => $adjustment->policy_after,
                'segments' => [['policy_adjustment_id' => $adjustment->id, 'amount' => $delta]],
            ]);
            $changes[] = ['usage_accrual_id' => $accrual->id, 'amount_delta' => $delta];
            $adjustment->usage_accrual_id = $accrual->id;
        } elseif ($delta !== 0) {
            // Settled ledgers remain immutable; only the outstanding difference affects the wallet.
            $metadata = ['category' => 'network_usage', 'correction' => true, 'policy_adjustment_id' => $adjustment->id];
            $transaction = $delta > 0
                ? $this->wallets->charge($customer, $delta, 'اصلاح تعرفه کارکرد شبکه', reference: $adjustment, metadata: $metadata)
                : $this->wallets->refund($customer, -$delta, 'بازپرداخت اصلاح تعرفه شبکه', reference: $adjustment, metadata: $metadata);
            $adjustment->wallet_transaction_id = $transaction->id;
        }
        $adjustment->accrual_changes = $changes;
        $adjustment->save();
    }
}
