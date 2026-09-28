<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class WorkspaceWalletRiskService
{
    public function __construct(private readonly WorkspaceWalletAlertService $alerts) {}

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{tone: string, label: string, percent: float}>
     */
    public function forProjects(Collection $projects, Customer $customer): array
    {
        $snapshots = [];
        $risks = [];

        foreach ($projects as $project) {
            $membership = $project->members->firstWhere('customer_id', $customer->id);
            if (! $membership?->canViewBilling() || ! $project->owner) {
                continue;
            }

            $ownerId = $project->owner_customer_id;
            if (! array_key_exists($ownerId, $snapshots)) {
                try {
                    $snapshots[$ownerId] = $this->alerts->snapshot($project->owner);
                } catch (Throwable $exception) {
                    report($exception);
                    $snapshots[$ownerId] = null;
                }
            }

            $snapshot = $snapshots[$ownerId];
            if ($snapshot === null) {
                continue;
            }
            $percent = $snapshot['percent'];
            if ($percent === null) {
                continue;
            }

            $thresholds = $this->alerts->thresholds($project);
            $warningPercent = $thresholds === [] ? 15 : max($thresholds);

            if ($snapshot['balance'] <= 0) {
                $tone = 'depleted';
                $label = $snapshot['balance'] < 0 ? 'موجودی منفی' : 'موجودی تمام شده';
            } elseif ($percent <= 5) {
                $tone = 'critical';
                $label = 'اعتبار بحرانی';
            } elseif ($percent <= $warningPercent) {
                $tone = 'warning';
                $label = 'اعتبار رو به پایان';
            } else {
                continue;
            }

            $risks[$project->id] = [
                'tone' => $tone,
                'label' => $label,
                'percent' => $percent,
            ];
        }

        return $risks;
    }
}
