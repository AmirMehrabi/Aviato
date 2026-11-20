<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\VirtualMachine;
use App\Models\VmNetworkBillingPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Morilog\Jalali\Jalalian;

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
        $instant = $instant->toImmutable()->utc();
        $periods = $vm->networkBillingPeriods();
        $existing = $this->storedPeriodAt($vm, $instant);
        if ($existing) {
            return $existing;
        }

        $policy = $this->effective($vm);
        [$start, $end, $policy['calendar']] = $this->periodBounds($instant->setTimezone($policy['timezone']));

        // Stored periods retain their dates even when a VM's timezone changes.
        $previous = (clone $periods)->where('period_end', '>', $start)
            ->where('period_end', '<=', $instant)->latest('period_end')->first();
        $next = (clone $periods)->where('period_start', '>', $instant)
            ->where('period_start', '<', $end)->oldest('period_start')->first();
        $start = $previous?->period_end ?? $start;
        $end = $next?->period_start ?? $end;

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

    public function periodDays(VirtualMachine $vm, CarbonImmutable $local): int
    {
        $period = $this->storedPeriodAt($vm, $local);
        [$start, $end] = $period
            ? [$period->period_start, $period->period_end]
            : $this->periodBounds($local);
        $timezone = $period?->timezone ?? $local->getTimezone();

        return (int) round($start->toImmutable()->setTimezone($timezone)->diffInDays($end->toImmutable()->setTimezone($timezone)));
    }

    private function storedPeriodAt(VirtualMachine $vm, CarbonImmutable $instant): ?VmNetworkBillingPeriod
    {
        $utc = $instant->utc();

        return $vm->networkBillingPeriods()->where('period_start', '<=', $utc)
            ->where('period_end', '>', $utc)->latest('period_start')->first();
    }

    /** @return array{CarbonImmutable, CarbonImmutable, string} */
    private function periodBounds(CarbonImmutable $local): array
    {
        $cutoverDate = config('network-billing.jalali_from');
        $cutover = null;
        if (filled($cutoverDate)) {
            if (! is_string($cutoverDate) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])-01$/D', $cutoverDate)) {
                throw new InvalidArgumentException('NETWORK_BILLING_JALALI_FROM must be the first day of a Gregorian month (YYYY-MM-01).');
            }

            $cutover = CarbonImmutable::parse($cutoverDate, $local->getTimezone());
        }

        if ($cutover === null || $local->lt($cutover)) {
            return [
                $local->startOfMonth()->utc(),
                $local->startOfMonth()->addMonth()->utc(),
                VmNetworkBillingPeriod::CALENDAR_GREGORIAN,
            ];
        }

        $month = Jalalian::fromCarbon($local->startOfDay()->toMutable())->getFirstDayOfMonth();
        $start = $month->toCarbon()->startOfDay()->toImmutable();
        $end = $month->addMonths()->toCarbon()->startOfDay()->toImmutable();
        if ($start->lt($cutover)) {
            return [$cutover->utc(), $end->utc(), VmNetworkBillingPeriod::CALENDAR_TRANSITION];
        }

        return [$start->utc(), $end->utc(), VmNetworkBillingPeriod::CALENDAR_JALALI];
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
