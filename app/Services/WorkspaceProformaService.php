<?php

namespace App\Services;

use App\Exceptions\VmPriceUnavailableException;
use App\Models\AppSetting;
use App\Models\Project;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Support\Jalali;
use Morilog\Jalali\Jalalian;

class WorkspaceProformaService
{
    public function __construct(private readonly BillingService $billing) {}

    public function estimate(Project $project): array
    {
        $generatedAt = Jalalian::fromCarbon(now()->setTimezone(config('app.customer_timezone', 'Asia/Tehran')));
        $rows = $project->virtualMachines()
            ->notDeleted()
            ->with(['bundle', 'infrastructureLocation'])
            ->get()
            ->sortBy([['display_name', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (VirtualMachine $vm): array {
                try {
                    $price = $this->billing->runningMonthlyEstimate($vm);
                } catch (VmPriceUnavailableException $exception) {
                    report($exception);
                    $price = null;
                }

                return ['vm' => $vm, 'price' => $price];
            });

        $totals = $rows->contains(fn (array $row): bool => $row['price'] === null)
            ? null
            : [
                'subtotal' => $rows->sum('price.subtotal'),
                'tax' => $rows->sum('price.tax'),
                'total' => $rows->sum('price.total'),
            ];

        return [
            'project' => $project->loadMissing('owner'),
            'rows' => $rows,
            'totals' => $totals,
            'currency' => AppSetting::currency(),
            'taxRate' => AppSetting::taxEnabled() ? AppSetting::taxRatePercentage() : 0,
            'hoursPerMonth' => ResourceRate::hoursPerMonth(),
            'generatedAt' => $generatedAt->format('Y/m/d H:i'),
            'referenceMonth' => Jalali::formatMonthYear($generatedAt->getYear(), $generatedAt->getMonth()),
            'periodStart' => $generatedAt->getFirstDayOfMonth()->toCarbon()->format('Y/m/d'),
            'periodEnd' => $generatedAt->getEndDayOfMonth()->toCarbon()->format('Y/m/d'),
        ];
    }
}
