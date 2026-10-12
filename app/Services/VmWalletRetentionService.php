<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Notifications\VmWalletRetentionNotification;
use Illuminate\Support\Facades\DB;

class VmWalletRetentionService
{
    public const DELETE_ACTOR = 'wallet_retention_expired';

    public function __construct(
        private readonly UsageBalanceService $balances,
        private readonly UsageBillingService $usage,
        private readonly VirtualMachineDeletionService $deletions,
        private readonly WorkspaceWalletAlertService $alerts,
    ) {}

    public function reconcile(Customer $owner, VirtualMachine $vm): bool
    {
        if ($vm->isDeleted()) {
            return false;
        }

        $funded = $this->balances->effectiveBalance($owner) > 0;
        if ($funded || ! $owner->auto_suspend_vms) {
            // A queued automatic deletion can be cancelled until its worker starts.
            if ($vm->isDeleting() && data_get($vm->remote_state, 'delete_requested.actor') === self::DELETE_ACTOR) {
                $this->allowDeletion($vm);
                $vm->refresh();
            }
            if (! $vm->isDeleting() && $vm->wallet_retention_expires_at) {
                $this->clearDeadline($vm);
            }

            return false;
        }

        if (! $vm->wallet_retention_expires_at && ! $vm->isDeleting()) {
            DB::transaction(function () use ($vm): void {
                $locked = VirtualMachine::query()->whereKey($vm->id)->lockForUpdate()->firstOrFail();
                if (! $locked->wallet_retention_expires_at) {
                    $locked->forceFill([
                        'wallet_depleted_at' => now(),
                        'wallet_retention_expires_at' => now()->addDays(AppSetting::customerWalletRetentionDays()),
                        'wallet_retention_notices' => [],
                    ])->save();
                }
            });
            $vm->refresh();
        }

        if (! $vm->wallet_retention_expires_at) {
            return false;
        }

        if ($vm->wallet_retention_expires_at->lte(now())) {
            if ($vm->password_reset_status === 'pending') {
                return true;
            }
            if ($this->balances->effectiveBalance($owner) <= 0) {
                $this->deletions->requestDelete($vm, self::DELETE_ACTOR);
            }

            return true;
        }

        $this->notify($vm, 'depleted');
        if (data_get($vm->wallet_restriction, 'wallet_stopped')) {
            $this->notify($vm, 'stopped');
        }
        $hours = now()->diffInSeconds($vm->wallet_retention_expires_at) / 3600;
        if ($hours <= 24) {
            $this->notify($vm, '24h');
        } elseif ($hours <= 48) {
            $this->notify($vm, '48h');
        }

        return false;
    }

    public function notify(VirtualMachine $vm, string $stage): void
    {
        $project = $vm->project;
        $owner = $project?->owner ?? $vm->customer;
        if (! $owner) {
            return;
        }
        $recipients = collect($project ? $this->alerts->recipients($project) : [])->push($owner)->unique('id');
        DB::transaction(function () use ($vm, $stage, $recipients): void {
            $locked = VirtualMachine::query()->whereKey($vm->id)->lockForUpdate()->firstOrFail();
            $notices = $locked->wallet_retention_notices ?? [];
            foreach ($recipients as $recipient) {
                $key = $stage.':'.$recipient->id;
                if (! isset($notices[$key])) {
                    $recipient->notify(new VmWalletRetentionNotification($locked, $stage));
                    $notices[$key] = now()->toISOString();
                }
            }
            $locked->forceFill(['wallet_retention_notices' => $notices])->save();
        });
    }

    public function allowDeletion(VirtualMachine $vm): bool
    {
        if (data_get($vm->remote_state, 'delete_requested.actor') !== self::DELETE_ACTOR) {
            return true;
        }
        $owner = $vm->project?->owner ?? $vm->customer;
        if ($owner && $owner->auto_suspend_vms && $vm->wallet_retention_expires_at?->lte(now())
            && $this->balances->effectiveBalance($owner) <= 0) {
            return true;
        }
        if ($vm->delete_started_at || data_get($vm->remote_state, 'wallet_delete_started_at')) {
            // Deletion has already crossed the irreversible boundary.
            return true;
        }
        $vm->forceFill([
            'status' => data_get($vm->remote_state, 'wallet_delete_previous_status', VirtualMachine::STATUS_STOPPED),
            'delete_requested_at' => null,
            'delete_failed_at' => null,
            'delete_error' => null,
            'desired_state' => array_merge($vm->desired_state ?? [], [
                'status' => data_get($vm->remote_state, 'wallet_delete_previous_status', VirtualMachine::STATUS_STOPPED),
            ]),
            'remote_state' => array_merge($vm->remote_state ?? [], ['delete_requested' => null]),
        ])->save();
        $this->clearDeadline($vm);

        return false;
    }

    private function clearDeadline(VirtualMachine $vm): void
    {
        // Accrue only through the old cutoff, then resume billing from funding time.
        $this->usage->accrueVm($vm);
        foreach ($vm->disks()->where('status', 'ready')->get() as $disk) {
            $this->usage->accrueExtraDisk($disk);
            $disk->forceFill(['last_billed_at' => now()])->save();
        }
        foreach ($vm->backups()->where('status', 'ready')->get() as $backup) {
            $this->usage->accrueBackup($backup);
            $backup->forceFill(['last_billed_at' => now()])->save();
        }
        $vm->refresh()->forceFill([
            'wallet_depleted_at' => null,
            'wallet_retention_expires_at' => null,
            'wallet_retention_notices' => null,
            'last_billed_at' => now(),
        ])->save();
    }

    /** @return array<string, mixed>|null */
    public function customerNotice(Project $project): ?array
    {
        if (! $project->owner->auto_suspend_vms || $this->balances->effectiveBalance($project->owner) > 0) {
            return null;
        }
        $machines = $project->virtualMachines()->notDeleted()->with(['bundle', 'disks', 'backups'])->get();
        $deadline = $machines->whereNotNull('wallet_retention_expires_at')->min('wallet_retention_expires_at');
        if (! $deadline) {
            return null;
        }
        $billing = app(BillingService::class);
        // The funding target covers every resource paid by the shared owner's wallet.
        $runningMonthly = VirtualMachine::query()->notDeleted()->where(function ($query) use ($project): void {
            $query->whereHas('project', fn ($query) => $query->where('owner_customer_id', $project->owner_customer_id))
                ->orWhere(fn ($query) => $query->whereNull('project_id')->where('customer_id', $project->owner_customer_id));
        })->with(['bundle', 'disks', 'backups'])->get()->sum(fn ($vm) => $billing->estimateMonthly($vm)
            + $vm->disks->where('status', 'ready')->sum(fn ($disk) => $billing->extraDiskHourly($disk) * ResourceRate::hoursPerMonth())
            + $vm->backups->where('status', 'ready')->sum(fn ($backup) => $billing->backupHourly($backup) * ResourceRate::hoursPerMonth()));
        $target = max(1, (int) ceil(-$this->balances->effectiveBalance($project->owner) + $runningMonthly * 24 / ResourceRate::hoursPerMonth()));

        return [
            'deadline' => $deadline,
            'expired' => $deadline->lte(now()),
            'count' => $machines->whereNotNull('wallet_retention_expires_at')->count(),
            'top_up_toman' => min(50000000, max(250000, (int) ceil($target / 1000) * 100)),
            'requires_multiple_payments' => $target > 500000000,
        ];
    }
}
