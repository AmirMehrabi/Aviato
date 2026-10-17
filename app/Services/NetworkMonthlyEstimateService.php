<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\NetworkUsageBucket;
use App\Models\Project;
use App\Models\VirtualMachine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class NetworkMonthlyEstimateService
{
    public function __construct(private readonly NetworkBillingPolicyService $policies) {}

    /** @return array{amount:int,pending:bool,included:bool} */
    public function forOwner(Customer $owner, ?Project $project = null): array
    {
        // The shared wallet pays for owned workspaces as well as legacy VMs.
        $machines = VirtualMachine::query()->notDeleted()->with('bundle')
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->when(! $project, fn ($query) => $query->where(fn ($query) => $query
                ->whereHas('project', fn ($project) => $project->where('owner_customer_id', $owner->id))
                ->orWhere(fn ($legacy) => $legacy->whereNull('project_id')->where('customer_id', $owner->id))))
            ->get();
        $amount = 0;
        $pending = false;
        $included = false;
        foreach ($machines as $vm) {
            $policy = $this->policies->effective($vm);
            if (! $policy['enabled']) {
                continue;
            }
            $included = true;
            $today = CarbonImmutable::now($policy['timezone'])->startOfDay();
            // Keep the projection stable until the next local day, including late revisions.
            $estimate = Cache::remember('network-monthly-estimate:v1:'.$vm->id.':'.$today->toDateString().':'.$policy['timezone'], $today->addDay(), function () use ($vm, $policy, $today): array {
                $from = $today->subDays(7);
                $buckets = NetworkUsageBucket::query()->where('virtual_machine_id', $vm->id)
                    ->whereIn('status', ['final', 'void'])->where('completeness', 'complete')
                    ->where('interval_start', '>=', $from->utc())->where('interval_end', '<=', $today->utc())
                    ->orderBy('interval_start')->get();
                $days = 0;
                $bytes = 0;
                for ($day = $from; $day->lt($today); $day = $day->addDay()) {
                    $end = $day->addDay();
                    if ($vm->created_at && $vm->created_at->gt($day)) {
                        continue;
                    }
                    $coveredUntil = $day->utc();
                    $dailyBytes = 0;
                    foreach ($buckets as $bucket) {
                        if ($bucket->interval_start->lt($day->utc()) || $bucket->interval_end->gt($end->utc())) {
                            continue;
                        }
                        // Gaps or overlapping intervals cannot safely represent a full day.
                        if (! $bucket->interval_start->equalTo($coveredUntil)) {
                            break;
                        }
                        $coveredUntil = CarbonImmutable::instance($bucket->interval_end);
                        if ($bucket->status === 'final') {
                            $dailyBytes += $this->policies->ratedBytes($policy['direction'], $bucket->ingress_bytes, $bucket->egress_bytes);
                        }
                    }
                    if ($coveredUntil->equalTo($end->utc())) {
                        $days++;
                        $bytes += $dailyBytes;
                    }
                }
                if ($days === 0) {
                    return ['amount' => null];
                }
                $projectedBytes = (int) round($bytes / $days * $today->daysInMonth);

                return ['amount' => $this->policies->amount($projectedBytes, $policy['included_bytes'], $policy['price_per_unit'], $policy['price_unit_bytes'])];
            });
            $pending = $pending || $estimate['amount'] === null;
            $amount += $estimate['amount'] ?? 0;
        }

        return compact('amount', 'pending', 'included');
    }

    public function roundedTotal(int $amount): int
    {
        $step = AppSetting::currency() === 'IRR' ? 100000 : 10000;

        return intdiv(max(0, $amount) + $step - 1, $step) * $step;
    }
}
