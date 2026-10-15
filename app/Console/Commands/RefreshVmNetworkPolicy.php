<?php

namespace App\Console\Commands;

use App\Models\VirtualMachine;
use App\Models\VmNetworkBillingPeriod;
use App\Services\NetworkBillingPolicyAdjustmentService;
use App\Services\NetworkBillingPolicyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshVmNetworkPolicy extends Command
{
    protected $signature = 'network:refresh-vm-policy {vm : VM UUID} {--price= : Override price in IRR} {--unit-bytes= : Override billing unit in bytes} {--apply : Save overrides and reconcile the current period}';

    protected $description = 'Preview or apply an auditable VM network policy correction';

    public function handle(NetworkBillingPolicyService $policies, NetworkBillingPolicyAdjustmentService $adjustments): int
    {
        $vm = VirtualMachine::query()->where('uuid', $this->argument('vm'))->firstOrFail();
        $overrides = [];
        foreach (['price' => 'network_overage_price_override', 'unit-bytes' => 'network_overage_price_unit_bytes_override'] as $option => $field) {
            $value = $this->option($option);
            if ($value === null) {
                continue;
            }
            if (! ctype_digit($value) || ($option === 'unit-bytes' && (int) $value < 1)) {
                $this->error('Price must be a nonnegative integer and unit-bytes must be a positive integer.');

                return self::FAILURE;
            }
            $overrides[$field] = (int) $value;
        }
        $previewVm = clone $vm;
        $previewVm->fill($overrides);
        $policy = $policies->effective($previewVm);
        $preview = VmNetworkBillingPeriod::query()->where('virtual_machine_id', $vm->id)
            ->where('status', 'open')->where('period_start', '<=', now())->where('period_end', '>', now())
            ->get()->map(fn ($period): array => [
                'period_id' => $period->id, 'price_before' => $period->price_per_unit,
                'price_after' => $policy['price_per_unit'], 'amount_before' => $period->accrued_amount,
                'amount_after' => $policies->amount(
                    $policies->ratedBytes($policy['direction'], $period->ingress_bytes, $period->egress_bytes),
                    $policy['included_bytes'], $policy['price_per_unit'], $policy['price_unit_bytes'],
                ),
            ])->all();
        $this->line(json_encode(['apply' => (bool) $this->option('apply'), 'periods' => $preview], JSON_THROW_ON_ERROR));
        if ($this->option('apply')) {
            DB::transaction(function () use ($vm, $overrides, $adjustments): void {
                $locked = VirtualMachine::query()->whereKey($vm->id)->lockForUpdate()->firstOrFail();
                if ($overrides !== []) {
                    $locked->fill($overrides)->save();
                }
                $adjustments->refresh($locked);
            });
            $this->info('VM overrides and current network billing period reconciled.');
        }

        return self::SUCCESS;
    }
}
