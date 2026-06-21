<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\UsageAccrual;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmDisk;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class UsageBalanceService
{
    public function __construct(private readonly BillingService $billing) {}

    public function customerPendingUsage(Customer $customer, ?CarbonInterface $until = null): int
    {
        $until ??= now();
        $settledPending = (int) UsageAccrual::query()
            ->where('customer_id', $customer->id)
            ->whereNull('settled_at')
            ->sum('amount');

        return $settledPending
            + $this->pendingVmQuery($this->customerVmQuery($customer), $until)
            + $this->pendingBackupQuery($this->customerBackupQuery($customer), $until)
            + $this->pendingDiskQuery($this->customerDiskQuery($customer), $until);
    }

    public function projectPendingUsage(int $projectId, ?CarbonInterface $until = null): int
    {
        $until ??= now();
        $settledPending = (int) UsageAccrual::query()
            ->where('project_id', $projectId)
            ->whereNull('settled_at')
            ->sum('amount');

        return $settledPending
            + $this->pendingVmQuery(VirtualMachine::query()->where('project_id', $projectId), $until)
            + $this->pendingBackupQuery(
                VmBackup::query()->whereHas('virtualMachine', fn (Builder $query) => $query->where('project_id', $projectId)),
                $until,
            )
            + $this->pendingDiskQuery(
                VmDisk::query()->whereHas('virtualMachine', fn (Builder $query) => $query->where('project_id', $projectId)),
                $until,
            );
    }

    public function effectiveBalance(Customer $customer, ?CarbonInterface $until = null): int
    {
        return $customer->wallet()->firstOrCreate([], ['balance' => 0])->balance
            - $this->customerPendingUsage($customer, $until);
    }

    private function customerVmQuery(Customer $customer): Builder
    {
        return VirtualMachine::query()->where(function (Builder $query) use ($customer): void {
            $query->whereHas('project', fn (Builder $project) => $project->where('owner_customer_id', $customer->id))
                ->orWhere(function (Builder $legacy) use ($customer): void {
                    $legacy->whereNull('project_id')->where('customer_id', $customer->id);
                });
        });
    }

    private function customerBackupQuery(Customer $customer): Builder
    {
        return VmBackup::query()->whereHas('virtualMachine', function (Builder $query) use ($customer): void {
            $query->whereHas('project', fn (Builder $project) => $project->where('owner_customer_id', $customer->id))
                ->orWhere(function (Builder $legacy) use ($customer): void {
                    $legacy->whereNull('project_id')->where('customer_id', $customer->id);
                });
        });
    }

    private function customerDiskQuery(Customer $customer): Builder
    {
        return VmDisk::query()->whereHas('virtualMachine', function (Builder $query) use ($customer): void {
            $query->whereHas('project', fn (Builder $project) => $project->where('owner_customer_id', $customer->id))
                ->orWhere(function (Builder $legacy) use ($customer): void {
                    $legacy->whereNull('project_id')->where('customer_id', $customer->id);
                });
        });
    }

    private function pendingVmQuery(Builder $query, CarbonInterface $until): int
    {
        return $query
            ->notDeleted()
            ->with('bundle')
            ->whereNotIn('status', [VirtualMachine::STATUS_DELETING, VirtualMachine::STATUS_DELETED])
            ->get()
            ->sum(function (VirtualMachine $vm) use ($until): int {
                if ($vm->isActionLocked() || $vm->status === VirtualMachine::STATUS_SUSPENDED) {
                    return 0;
                }

                $from = $vm->last_billed_at ?? $vm->created_at ?? $until;
                $seconds = max(0, $from->diffInSeconds($until));
                $hourly = $vm->isRunning()
                    ? $this->billing->hourlyWhenRunning($vm)
                    : $this->billing->persistentHourly($vm);

                return max(0, (int) ($vm->unbilled_amount ?? 0) + (int) round($seconds * $hourly / 3600));
            });
    }

    private function pendingBackupQuery(Builder $query, CarbonInterface $until): int
    {
        return $query
            ->with('virtualMachine')
            ->where('status', VmBackup::STATUS_READY)
            ->where('size_bytes', '>', 0)
            ->get()
            ->sum(function (VmBackup $backup) use ($until): int {
                $from = $backup->last_billed_at ?? $backup->finished_at ?? $backup->created_at ?? $until;
                $seconds = max(0, $from->diffInSeconds($until));

                return max(0, (int) round($seconds * $this->billing->backupHourly($backup) / 3600));
            });
    }

    private function pendingDiskQuery(Builder $query, CarbonInterface $until): int
    {
        return $query
            ->with('virtualMachine')
            ->where('status', VmDisk::STATUS_READY)
            ->get()
            ->sum(function (VmDisk $disk) use ($until): int {
                $from = $disk->last_billed_at ?? $disk->created_at ?? $until;
                $seconds = max(0, $from->diffInSeconds($until));

                return max(0, (int) round($seconds * $this->billing->extraDiskHourly($disk) / 3600));
            });
    }
}
