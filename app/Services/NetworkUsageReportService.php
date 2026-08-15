<?php

namespace App\Services;

use App\Models\NetworkUsageBucket;
use App\Models\VirtualMachine;
use App\Models\VmNetworkBillingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class NetworkUsageReportService
{
    /** @return array<string, mixed> */
    public function vmSummary(VirtualMachine $vm, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $period = VmNetworkBillingPeriod::query()
            ->where('virtual_machine_id', $vm->id)
            ->where('period_start', '<=', $at)
            ->where('period_end', '>', $at)
            ->latest('period_start')->first()
            ?? VmNetworkBillingPeriod::query()->where('virtual_machine_id', $vm->id)->latest('period_start')->first();
        $latestBucket = NetworkUsageBucket::query()->where('virtual_machine_id', $vm->id)->latest('interval_end')->first();

        if (! $period) {
            return ['period' => null, 'latest_bucket' => $latestBucket, 'ingress_bytes' => 0, 'egress_bytes' => 0,
                'rated_bytes' => 0, 'included_bytes' => 0, 'remaining_bytes' => 0, 'billable_bytes' => 0,
                'accrued_amount' => 0, 'usage_percent' => 0, 'freshness' => $this->freshness($latestBucket)];
        }

        return [
            'period' => $period, 'latest_bucket' => $latestBucket,
            'ingress_bytes' => (int) $period->ingress_bytes, 'egress_bytes' => (int) $period->egress_bytes,
            'rated_bytes' => (int) $period->rated_bytes, 'included_bytes' => (int) $period->included_bytes,
            'remaining_bytes' => max(0, (int) $period->included_bytes - (int) $period->rated_bytes),
            'billable_bytes' => (int) $period->billable_bytes, 'accrued_amount' => (int) $period->accrued_amount,
            'usage_percent' => $period->included_bytes > 0 ? round($period->rated_bytes * 100 / $period->included_bytes, 1) : null,
            'freshness' => $this->freshness($latestBucket),
        ];
    }

    /** @return Collection<int, object> */
    public function daily(VirtualMachine $vm, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return NetworkUsageBucket::query()
            ->where('virtual_machine_id', $vm->id)->where('status', 'final')->where('completeness', 'complete')
            ->where('interval_start', '>=', $from)->where('interval_end', '<=', $to)
            ->selectRaw('DATE(interval_start) as usage_date, SUM(ingress_bytes) as ingress_bytes, SUM(egress_bytes) as egress_bytes, COUNT(*) as bucket_count')
            ->groupByRaw('DATE(interval_start)')->orderBy('usage_date')->get();
    }

    public function bytes(int $bytes): string
    {
        foreach ([['TiB', 1099511627776], ['GiB', 1073741824], ['MiB', 1048576], ['KiB', 1024]] as [$unit, $size]) {
            if ($bytes >= $size) {
                return number_format($bytes / $size, $unit === 'TiB' ? 2 : 1).' '.$unit;
            }
        }

        return number_format($bytes).' B';
    }

    /** @return array{label:string,tone:string} */
    private function freshness(?NetworkUsageBucket $bucket): array
    {
        if (! $bucket) {
            return ['label' => 'هنوز داده‌ای دریافت نشده', 'tone' => 'unknown'];
        }
        $minutes = $bucket->interval_end->diffInMinutes(now());
        if ($minutes <= 120) {
            return ['label' => 'به‌روز', 'tone' => 'healthy'];
        }
        if ($minutes <= 360) {
            return ['label' => 'با تأخیر', 'tone' => 'warning'];
        }

        return ['label' => 'داده قدیمی', 'tone' => 'danger'];
    }
}
