<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmDisk;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class UsageBillingService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly WalletService $wallets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function estimateVmUsage(VirtualMachine $vm, ?CarbonInterface $until = null): array
    {
        $until ??= now();
        $from = $vm->last_billed_at ?? $vm->created_at ?? $until;

        if ($vm->isActionLocked()) {
            return [
                'from' => $from,
                'until' => $until,
                'hours' => 0,
                'hourly_rate' => 0,
                'amount' => 0,
                'status' => $vm->status,
                'is_running' => false,
            ];
        }

        $hours = max(0, $from->floatDiffInHours($until));
        $hourly = $vm->isRunning()
            ? $this->billing->hourlyWhenRunning($vm)
            : $this->billing->persistentHourly($vm);
        $baseAmount = (int) ($vm->unbilled_amount ?? 0);
        $computedAmount = (int) round($hours * $hourly);

        return [
            'from' => $from,
            'until' => $until,
            'hours' => $hours,
            'hourly_rate' => $hourly,
            'amount' => max(0, $baseAmount + $computedAmount),
            'status' => $vm->status,
            'is_running' => $vm->isRunning(),
        ];
    }

    public function chargeVm(VirtualMachine $vm, ?CarbonInterface $until = null): ?WalletTransaction
    {
        $vm->loadMissing(['customer', 'bundle', 'project.owner', 'creator']);
        $billingCustomer = $vm->project?->owner ?? $vm->customer;
        $usage = $this->estimateVmUsage($vm, $until);

        if ($usage['amount'] <= 0 || ! $billingCustomer) {
            return null;
        }

        $transaction = $this->wallets->charge(
            $billingCustomer,
            $usage['amount'],
            'کسر کارکرد PAYG برای ماشین مجازی '.$vm->name,
            metadata: [
                'category' => 'payg_usage',
                'vm_id' => $vm->id,
                'vm_name' => $vm->name,
                'project_id' => $vm->project_id,
                'project_name' => $vm->project?->name,
                'project_owner_id' => $billingCustomer->id,
                'created_by_customer_id' => $vm->created_by_customer_id,
                'bundle_id' => $vm->vm_bundle_id,
                'period_start' => $usage['from']->toIso8601String(),
                'period_end' => $usage['until']->toIso8601String(),
                'hours' => round($usage['hours'], 4),
                'hourly_rate' => $usage['hourly_rate'],
                'resource_snapshot' => [
                    'cpu_cores' => $vm->cpu_cores,
                    'ram_gb' => $vm->ram_gb,
                    'disk_gb' => $vm->disk_gb,
                    'ip_count' => $vm->ip_count,
                    'status' => $vm->status,
                    'bundle_name' => $vm->bundle?->name,
                ],
            ],
        );

        $vm->forceFill([
            'last_billed_at' => $usage['until'],
            'unbilled_amount' => 0,
        ])->save();

        return $transaction;
    }

    /**
     * @return Collection<int, WalletTransaction>
     */
    public function chargeAllDueUsage(?CarbonInterface $until = null): Collection
    {
        $transactions = new Collection;
        $until ??= now();

        VirtualMachine::query()
            ->notDeleted()
            ->with(['customer', 'bundle', 'project.owner', 'creator'])
            ->whereNotIn('status', [VirtualMachine::STATUS_DELETING, VirtualMachine::STATUS_DELETED])
            ->orderBy('project_id')
            ->chunk(100, function ($vms) use (&$transactions, $until): void {
                foreach ($vms as $vm) {
                    $transaction = $this->chargeVm($vm, $until);

                    if ($transaction) {
                        $transactions->push($transaction);
                    }
                }
            });

        VmBackup::query()
            ->with('virtualMachine.project.owner', 'virtualMachine.customer')
            ->where('status', VmBackup::STATUS_READY)
            ->where('size_bytes', '>', 0)
            ->chunk(100, function ($backups) use (&$transactions, $until): void {
                foreach ($backups as $backup) {
                    $transaction = $this->chargeBackup($backup, $until);

                    if ($transaction) {
                        $transactions->push($transaction);
                    }
                }
            });

        VmDisk::query()
            ->with('virtualMachine.project.owner', 'virtualMachine.customer')
            ->where('status', VmDisk::STATUS_READY)
            ->chunk(100, function ($disks) use (&$transactions, $until): void {
                foreach ($disks as $disk) {
                    $transaction = $this->chargeExtraDisk($disk, $until);

                    if ($transaction) {
                        $transactions->push($transaction);
                    }
                }
            });

        return $transactions;
    }

    public function chargeBackup(VmBackup $backup, ?CarbonInterface $until = null): ?WalletTransaction
    {
        $backup->loadMissing('virtualMachine.project.owner', 'virtualMachine.customer');
        $until ??= now();
        $vm = $backup->virtualMachine;
        $billingCustomer = $vm?->project?->owner ?? $vm?->customer;
        $from = $backup->last_billed_at ?? $backup->finished_at ?? $backup->created_at ?? $until;
        $hours = max(0, $from->floatDiffInHours($until));
        $hourly = $this->billing->backupHourly($backup);
        $amount = (int) round($hours * $hourly);

        if ($amount <= 0 || ! $billingCustomer || ! $vm) {
            return null;
        }

        $transaction = $this->wallets->charge(
            $billingCustomer,
            $amount,
            'کسر فضای بکاپ برای ماشین مجازی '.$vm->name,
            metadata: [
                'category' => 'backup_storage',
                'vm_id' => $backup->virtual_machine_id,
                'vm_name' => $vm->name,
                'project_id' => $vm->project_id,
                'project_name' => $vm->project?->name,
                'project_owner_id' => $billingCustomer->id,
                'created_by_customer_id' => $vm->created_by_customer_id,
                'backup_id' => $backup->id,
                'period_start' => $from->toIso8601String(),
                'period_end' => $until->toIso8601String(),
                'hours' => round($hours, 4),
                'hourly_rate' => $hourly,
                'backup_snapshot' => [
                    'size_gb' => round($backup->sizeGb(), 4),
                    'size_bytes' => $backup->size_bytes,
                    'volid' => $backup->volid,
                    'storage' => $backup->storage,
                ],
            ],
        );

        $backup->forceFill(['last_billed_at' => $until])->save();

        return $transaction;
    }

    public function chargeExtraDisk(VmDisk $disk, ?CarbonInterface $until = null): ?WalletTransaction
    {
        $disk->loadMissing('virtualMachine.project.owner', 'virtualMachine.customer');
        $until ??= now();
        $vm = $disk->virtualMachine;
        $billingCustomer = $vm?->project?->owner ?? $vm?->customer;
        $from = $disk->last_billed_at ?? $disk->created_at ?? $until;
        $hours = max(0, $from->floatDiffInHours($until));
        $hourly = $this->billing->extraDiskHourly($disk);
        $amount = (int) round($hours * $hourly);

        if ($amount <= 0 || ! $billingCustomer || ! $vm) {
            return null;
        }

        $transaction = $this->wallets->charge(
            $billingCustomer,
            $amount,
            'کسر فضای دیسک اضافه برای ماشین مجازی '.$vm->name,
            metadata: [
                'category' => 'extra_disk_storage',
                'vm_id' => $disk->virtual_machine_id,
                'vm_name' => $vm->name,
                'project_id' => $vm->project_id,
                'project_name' => $vm->project?->name,
                'project_owner_id' => $billingCustomer->id,
                'created_by_customer_id' => $vm->created_by_customer_id,
                'disk_id' => $disk->id,
                'period_start' => $from->toIso8601String(),
                'period_end' => $until->toIso8601String(),
                'hours' => round($hours, 4),
                'hourly_rate' => $hourly,
                'disk_snapshot' => [
                    'disk_device' => $disk->disk_device,
                    'size_gb' => $disk->size_gb,
                    'storage' => $disk->storage,
                ],
            ],
        );

        $disk->forceFill(['last_billed_at' => $until])->save();

        return $transaction;
    }

    public function customerPendingUsage(Customer $customer): int
    {
        return $customer->virtualMachines()
            ->notDeleted()
            ->with('bundle')
            ->get()
            ->sum(fn (VirtualMachine $vm): int => $this->estimateVmUsage($vm)['amount']);
    }

    public function projectPendingUsage(int $projectId): int
    {
        return VirtualMachine::query()
            ->where('project_id', $projectId)
            ->notDeleted()
            ->with('bundle')
            ->get()
            ->sum(fn (VirtualMachine $vm): int => $this->estimateVmUsage($vm)['amount']);
    }
}
