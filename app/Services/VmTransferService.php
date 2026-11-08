<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use App\Models\VirtualMachine;
use App\Models\VmTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VmTransferService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly BillingService $billingService,
    ) {}

    /**
     * Transfer a VM from one customer to another with full audit trail
     *
     * @throws \Exception
     */
    public function transferVm(
        VirtualMachine $vm,
        Customer $toCustomer,
        int $initiatedByUserId,
        ?string $notes = null,
        ?Project $toProject = null,
    ): VmTransfer {
        // Validation
        $toProject ??= $toCustomer->ensureDefaultProject();
        $this->validateTransfer($vm, $toCustomer, $toProject);

        return DB::transaction(function () use ($vm, $toCustomer, $toProject, $initiatedByUserId, $notes) {
            $vm = VirtualMachine::query()->whereKey($vm->id)->lockForUpdate()->firstOrFail();
            $this->validateTransfer($vm, $toCustomer, $toProject);
            $fromCustomer = $vm->customer;
            $fromProject = $vm->project;

            // Create snapshot before transfer
            $snapshotBefore = $this->createSnapshot($vm);

            // Calculate and handle unbilled amount
            $at = now();
            app(UsageBillingService::class)->accrueVm($vm, $at);
            foreach ($vm->backups()->where('status', 'ready')->get() as $backup) {
                app(UsageBillingService::class)->accrueBackup($backup, $at);
            }
            foreach ($vm->disks()->where('status', 'ready')->get() as $disk) {
                app(UsageBillingService::class)->accrueExtraDisk($disk, $at);
            }
            $vm->refresh();
            $unbilledAmount = 0; // Elapsed usage belongs to the old owner; only future usage moves.

            // Create transfer record
            $transfer = VmTransfer::create([
                'virtual_machine_id' => $vm->id,
                'from_customer_id' => $fromCustomer->id,
                'to_customer_id' => $toCustomer->id,
                'from_project_id' => $fromProject?->id,
                'to_project_id' => $toProject->id,
                'initiated_by_user_id' => $initiatedByUserId,
                'unbilled_amount_transferred' => $unbilledAmount,
                'notes' => $notes,
                'snapshot_before' => $snapshotBefore,
            ]);

            // Update VM ownership
            $vm->update([
                'customer_id' => $toCustomer->id,
                'project_id' => $toProject->id,
                'unbilled_amount' => 0, // Already accrued to the previous owner
                'last_billed_at' => $at, // Ownership boundary already accrued above
            ]);

            // Create snapshot after transfer
            $snapshotAfter = $this->createSnapshot($vm->fresh());

            // Complete the transfer
            $transfer->update([
                'snapshot_after' => $snapshotAfter,
                'completed_at' => now(),
            ]);

            // Log the transfer
            Log::info('VM transferred', [
                'vm_id' => $vm->id,
                'vm_name' => $vm->name,
                'from_customer_id' => $fromCustomer->id,
                'to_customer_id' => $toCustomer->id,
                'unbilled_amount' => $unbilledAmount,
                'transfer_id' => $transfer->id,
                'initiated_by' => $initiatedByUserId,
            ]);

            return $transfer;
        });
    }

    /**
     * Validate that the transfer can proceed
     *
     * @throws \Exception
     */
    private function validateTransfer(VirtualMachine $vm, Customer $toCustomer, Project $toProject): void
    {
        // Check if VM is in a transferable state
        if ($vm->isActionLocked()) {
            throw new \Exception('ماشینی که در حال حذف است یا حذف شده، قابل انتقال نیست.');
        }

        // Check if VM has pending operations
        if ($vm->provisioning_status === VirtualMachine::PROVISION_PENDING) {
            throw new \Exception('ماشینی که هنوز در حال ساخت است، قابل انتقال نیست.');
        }

        // Check if there are pending upgrade orders
        if ($vm->pendingUpgradeOrders()->exists()) {
            throw new \Exception('این ماشین سفارش ارتقای در حال انجام دارد و فعلا قابل انتقال نیست.');
        }

        // Check if target customer is active
        if ($toCustomer->status !== 'active') {
            throw new \Exception('انتقال ماشین به مشتری غیرفعال مجاز نیست.');
        }

        // Check if transferring to the same customer
        if ($vm->customer_id === $toCustomer->id) {
            throw new \Exception('این ماشین همین حالا متعلق به این مشتری است.');
        }

        if ((int) $toProject->owner_customer_id !== (int) $toCustomer->id) {
            throw new \Exception('فضای کاری انتخاب‌شده برای مشتری مقصد نیست.');
        }
    }

    /**
     * Create a snapshot of the VM's current state
     */
    private function createSnapshot(VirtualMachine $vm): array
    {
        return [
            'pricing' => $vm->pricingSnapshot(),
            'vm_id' => $vm->id,
            'vm_name' => $vm->name,
            'customer_id' => $vm->customer_id,
            'customer_name' => $vm->customer?->name,
            'project_id' => $vm->project_id,
            'project_name' => $vm->project?->name,
            'status' => $vm->status,
            'provisioning_status' => $vm->provisioning_status,
            'cpu_cores' => $vm->cpu_cores,
            'ram_gb' => $vm->ram_gb,
            'disk_gb' => $vm->disk_gb,
            'unbilled_amount' => $vm->unbilled_amount,
            'last_billed_at' => $vm->last_billed_at?->toIso8601String(),
            'monthly_cost_estimate' => $vm->isRunning()
                ? $this->billingService->estimateMonthly($vm)
                : $this->billingService->estimateStoppedMonthly($vm),
        ];
    }

    /**
     * Get transfer history for a VM
     */
    public function getTransferHistory(VirtualMachine $vm)
    {
        return VmTransfer::where('virtual_machine_id', $vm->id)
            ->with(['fromCustomer', 'toCustomer', 'fromProject', 'toProject', 'initiatedBy'])
            ->orderByDesc('created_at')
            ->get();
    }
}
