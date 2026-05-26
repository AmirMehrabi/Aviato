<?php

namespace App\Services;

use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use Illuminate\Support\Collection;

class BillingService
{
    public function estimateMonthly(VirtualMachine $vm): int
    {
        return (int) round($this->hourlyWhenRunning($vm) * ResourceRate::hoursPerMonth());
    }

    public function estimateStoppedMonthly(VirtualMachine $vm): int
    {
        return (int) round($this->persistentHourly($vm) * ResourceRate::hoursPerMonth());
    }

    public function currentAccrued(VirtualMachine $vm): int
    {
        $from = $vm->last_billed_at ?? $vm->created_at ?? now();
        $hours = max(0, $from->floatDiffInHours(now()));
        $hourly = $vm->isRunning() ? $this->hourlyWhenRunning($vm) : $this->persistentHourly($vm);

        return (int) ($vm->unbilled_amount ?? 0) + (int) round($hours * $hourly);
    }

    public function hourlyWhenRunning(VirtualMachine $vm): float
    {
        if ($vm->bundle) {
            return (float) $vm->bundle->hourly_price;
        }

        $rates = $this->rates();

        return ($vm->cpu_cores * $this->rate($rates, ResourceRate::CPU))
            + ($vm->ram_gb * $this->rate($rates, ResourceRate::RAM))
            + ($vm->disk_gb * $this->rate($rates, ResourceRate::DISK))
            + ($vm->ip_count * $this->rate($rates, ResourceRate::IP));
    }

    public function persistentHourly(VirtualMachine $vm): float
    {
        $rates = $this->rates();

        return ($vm->disk_gb * $this->rate($rates, ResourceRate::DISK))
            + ($vm->ip_count * $this->rate($rates, ResourceRate::IP));
    }

    public function backupHourly(VmBackup $backup): float
    {
        $rates = $this->rates();

        return $backup->sizeGb() * $this->rate($rates, ResourceRate::BACKUP);
    }

    public function customerSummary(int $customerId): array
    {
        $vms = VirtualMachine::query()
            ->notDeleted()
            ->with('bundle')
            ->where('customer_id', $customerId)
            ->get();

        return [
            'running' => $vms->where('status', VirtualMachine::STATUS_RUNNING)->count(),
            'stopped' => $vms->where('status', VirtualMachine::STATUS_STOPPED)->count(),
            'monthly_spend' => $vms
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => $vm->isRunning() ? $this->estimateMonthly($vm) : $this->estimateStoppedMonthly($vm)),
            'unbilled_accrued' => $vms
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => $this->currentAccrued($vm)),
        ];
    }

    /** @return Collection<string, ResourceRate> */
    private function rates(): Collection
    {
        return ResourceRate::query()->where('is_active', true)->get()->keyBy('resource');
    }

    /** @param Collection<string, ResourceRate> $rates */
    private function rate(Collection $rates, string $resource): float
    {
        return (float) ($rates->get($resource)?->hourly_price ?? 0);
    }
}
