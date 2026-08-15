<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\VirtualMachine;
use App\Models\VmNetworkBillingPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class NetworkBillingPolicyService
{
    /** @return array<string, mixed> */
    public function effective(VirtualMachine $vm): array
    {
        $vm->loadMissing('bundle');
        $bundle = $vm->bundle;

        return [
            'enabled' => $vm->network_accounting_enabled_override ?? (bool) ($bundle?->network_accounting_enabled ?? false),
            'included_bytes' => $vm->network_included_bytes_monthly_override ?? (int) ($bundle?->network_included_bytes_monthly ?? 1099511627776),
            'price_per_unit' => $vm->network_overage_price_override ?? (int) ($bundle?->network_overage_price ?? 9000),
            'price_unit_bytes' => $vm->network_overage_price_unit_bytes_override ?? (int) ($bundle?->network_overage_price_unit_bytes ?? 1073741824),
            'direction' => $vm->network_usage_direction_override ?? ($bundle?->network_usage_direction ?? 'both'),
            'timezone' => $vm->network_billing_timezone_override ?? ($bundle?->network_billing_timezone ?? 'Asia/Tehran'),
            'currency' => AppSetting::currency(),
            'bundle_id' => $vm->vm_bundle_id,
        ];
    }

    public function periodFor(VirtualMachine $vm, CarbonInterface $instant): VmNetworkBillingPeriod
    {
        $policy = $this->effective($vm);
        $local = CarbonImmutable::instance($instant->toImmutable())->setTimezone($policy['timezone']);
        $start = $local->startOfMonth()->utc();
        $end = $local->addMonthNoOverflow()->startOfMonth()->utc();
        $customer = $vm->project?->owner ?? $vm->customer;

        return VmNetworkBillingPeriod::query()->firstOrCreate([
            'virtual_machine_id' => $vm->id,
            'period_start' => $start,
        ], [
            'customer_id' => $customer->id,
            'project_id' => $vm->project_id,
            'period_end' => $end,
            'timezone' => $policy['timezone'],
            'direction' => $policy['direction'],
            'included_bytes' => $policy['included_bytes'],
            'price_per_unit' => $policy['price_per_unit'],
            'price_unit_bytes' => $policy['price_unit_bytes'],
            'currency' => $policy['currency'],
            'policy_snapshot' => $policy,
        ]);
    }

    public function ratedBytes(string $direction, int $ingress, int $egress): int
    {
        return match ($direction) {
            'ingress' => $ingress,
            'egress' => $egress,
            default => $ingress + $egress,
        };
    }

    public function amount(int $ratedBytes, int $includedBytes, int $price, int $unitBytes): int
    {
        $billable = max(0, $ratedBytes - $includedBytes);

        return $unitBytes > 0 ? intdiv($billable * $price, $unitBytes) : 0;
    }
}
