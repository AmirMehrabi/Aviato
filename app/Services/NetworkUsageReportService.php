<?php

namespace App\Services;

use App\Models\NetworkUsageBucket;
use App\Models\VirtualMachine;
use App\Models\VmNetworkBillingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

class NetworkUsageReportService
{
    /** @return array<string, mixed> */
    public function vmDetail(VirtualMachine $vm): array
    {
        $summary = $this->vmSummary($vm);
        $from = $summary['period']?->period_start ?? now()->startOfMonth();
        $to = $summary['period']?->period_end ?? now()->endOfMonth();

        return [
            'summary' => $summary,
            'reports' => $this,
            'daily' => $this->daily($vm, $from, $to),
            'buckets' => NetworkUsageBucket::query()->where('virtual_machine_id', $vm->id)
                ->latest('interval_start')->paginate(25, ['*'], 'network_page')->withQueryString()->fragment('network'),
        ];
    }

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

    /** @return array<string, Jalalian> */
    public function trafficMonths(VirtualMachine $vm, string $timezone): array
    {
        $month = Jalalian::fromCarbon(now($timezone)->startOfDay())->getFirstDayOfMonth();
        $earliest = NetworkUsageBucket::query()->where('virtual_machine_id', $vm->id)
            ->oldest('interval_start')->first(['interval_start']);
        $firstMonth = $earliest
            ? Jalalian::fromCarbon($earliest->interval_start->setTimezone($timezone)->startOfDay())->getFirstDayOfMonth()
            : $month;

        $months = [$month->format('Y-m') => $month];
        while ($month->format('Y-m') > $firstMonth->format('Y-m')) {
            $month = $month->subMonths();
            $months[$month->format('Y-m')] = $month;
        }

        return $months;
    }

    /** @return array{daily: Collection<int, object>, dataState: array{partial: int, missing: int}} */
    public function trafficForRange(VirtualMachine $vm, CarbonInterface $from, CarbonInterface $to): array
    {
        $buckets = NetworkUsageBucket::query()->where('virtual_machine_id', $vm->id)
            ->where('interval_start', '>=', $from->toImmutable()->utc())
            ->where('interval_start', '<', $to->toImmutable()->utc())
            ->where('interval_end', '<=', $to->toImmutable()->utc())
            ->select('interval_start', 'ingress_bytes', 'egress_bytes', 'status', 'completeness')
            ->orderBy('interval_start')
            ->cursor();

        $days = [];
        $dataState = ['partial' => 0, 'missing' => 0];
        foreach ($buckets as $bucket) {
            if (isset($dataState[$bucket->completeness])) {
                $dataState[$bucket->completeness]++;
            }
            if ($bucket->status !== 'final' || $bucket->completeness !== 'complete') {
                continue;
            }

            $date = $bucket->interval_start->setTimezone($from->getTimezone())->toDateString();
            $day = $days[$date] ??= (object) ['usage_date' => $date, 'ingress_bytes' => 0, 'egress_bytes' => 0];
            $day->ingress_bytes += $bucket->ingress_bytes;
            $day->egress_bytes += $bucket->egress_bytes;
        }

        return ['daily' => collect(array_values($days)), 'dataState' => $dataState];
    }

    public function gigabytes(int $bytes): string
    {
        return $bytes > 0 && $bytes < 1_000_000 ? '<0.001' : number_format($bytes / 1_000_000_000, 3);
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
