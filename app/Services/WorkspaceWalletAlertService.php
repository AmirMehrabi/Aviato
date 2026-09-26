<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmDisk;
use App\Notifications\WorkspaceWalletBalanceNotification;
use App\Services\Sms\KavenegarLookupClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkspaceWalletAlertService
{
    public function __construct(
        private readonly UsageBalanceService $balances,
        private readonly BillingService $billing,
    ) {}

    public function snapshot(Customer $owner): array
    {
        $balance = $this->balances->effectiveBalance($owner);
        $hours = ResourceRate::hoursPerMonth();
        $monthly = 0;
        $machines = VirtualMachine::query()
            ->notDeleted()
            ->where(function ($query) use ($owner): void {
                $query->whereHas('project', fn ($project) => $project->where('owner_customer_id', $owner->id))
                    ->orWhere(fn ($legacy) => $legacy->whereNull('project_id')->where('customer_id', $owner->id));
            })
            ->with(['bundle', 'disks', 'backups'])
            ->get();

        foreach ($machines as $vm) {
            if ($vm->isActionLocked() || $vm->status === VirtualMachine::STATUS_SUSPENDED) {
                continue;
            }

            $monthly += $vm->isRunning() ? $this->billing->estimateMonthly($vm) : $this->billing->estimateStoppedMonthly($vm);
            $monthly += $vm->disks->where('status', VmDisk::STATUS_READY)
                ->sum(fn (VmDisk $disk): int => (int) round($this->billing->extraDiskHourly($disk) * $hours));
            $monthly += $vm->backups->where('status', VmBackup::STATUS_READY)->where('size_bytes', '>', 0)
                ->sum(fn (VmBackup $backup): int => (int) round($this->billing->backupHourly($backup) * $hours));
        }

        return [
            'balance' => $balance,
            'monthly_cost' => $monthly,
            'percent' => $monthly > 0 ? round(100 * $balance / $monthly, 2) : null,
        ];
    }

    public function thresholds(Project $project): array
    {
        return $project->wallet_alert_thresholds ?? AppSetting::customerWalletAlertPercentages();
    }

    public function recipients(Project $project): array
    {
        $ids = $project->wallet_alert_recipient_ids;
        if ($ids === null) {
            $ids = [$project->owner_customer_id];
            if (AppSetting::customerWalletAlertRecipientPolicy() === 'owner_and_billing') {
                $ids = array_merge($ids, $project->members()->where('role', 'billing')->pluck('customer_id')->all());
            }
        }

        return $project->members()->with('customer')
            ->whereIn('customer_id', $ids)
            ->get()
            ->pluck('customer')
            ->filter(fn (?Customer $customer): bool => $customer && $customer->status === Customer::STATUS_ACTIVE)
            ->values()
            ->all();
    }

    public function checkOwner(Customer $owner): void
    {
        $snapshot = $this->snapshot($owner);

        foreach ($owner->ownedProjects()->get() as $project) {
            $this->checkProject($project, $snapshot);
        }
    }

    public function checkProject(Project $project, ?array $snapshot = null): void
    {
        $snapshot ??= $this->snapshot($project->owner);
        $thresholds = $this->thresholds($project);
        $recipients = $this->recipients($project);

        DB::transaction(function () use ($project, $snapshot, $thresholds, $recipients): void {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $recipientIds = array_map(fn (Customer $customer): int => $customer->id, $recipients);
            DB::table('workspace_wallet_alert_states')->where('project_id', $project->id)
                ->when($recipientIds !== [], fn ($query) => $query->whereNotIn('customer_id', $recipientIds))
                ->when($recipientIds === [], fn ($query) => $query)
                ->delete();

            if ($snapshot['percent'] === null) {
                DB::table('workspace_wallet_alert_states')->where('project_id', $project->id)->delete();

                return;
            }

            DB::table('workspace_wallet_alert_states')->where('project_id', $project->id)
                ->whereNotIn('threshold_percent', $thresholds)->delete();

            foreach ($recipients as $recipient) {
                $newlyCrossed = [];

                foreach ($thresholds as $threshold) {
                    $state = DB::table('workspace_wallet_alert_states')
                        ->where('project_id', $project->id)
                        ->where('customer_id', $recipient->id)
                        ->where('threshold_percent', $threshold);

                    if ($threshold * $snapshot['monthly_cost'] < $snapshot['balance'] * 100) {
                        $state->delete();

                        continue;
                    }

                    if ($state->exists()) {
                        continue;
                    }

                    DB::table('workspace_wallet_alert_states')->insert([
                        'project_id' => $project->id,
                        'customer_id' => $recipient->id,
                        'threshold_percent' => $threshold,
                        'notified_at' => now(),
                    ]);
                    $newlyCrossed[] = $threshold;
                }

                if ($newlyCrossed !== []) {
                    $this->deliver($project, $recipient, $snapshot, min($newlyCrossed));
                }
            }
        });
    }

    public function sendManual(Project $project, int $adminId): int
    {
        $snapshot = $this->snapshot($project->owner);
        $recipients = $this->recipients($project);

        foreach ($recipients as $recipient) {
            $this->deliver($project, $recipient, $snapshot, null, $adminId);
        }

        return count($recipients);
    }

    private function deliver(Project $project, Customer $recipient, array $snapshot, ?int $threshold, ?int $adminId = null): void
    {
        $recipient->notify(new WorkspaceWalletBalanceNotification($project, $snapshot['balance'], $snapshot['percent'], $threshold, $adminId));
        DB::table('workspace_wallet_alert_deliveries')->insert([
            'project_id' => $project->id,
            'customer_id' => $recipient->id,
            'sent_by_admin_id' => $adminId,
            'kind' => $threshold === null ? 'manual' : 'automatic',
            'threshold_percent' => $threshold,
            'effective_balance' => $snapshot['balance'],
            'remaining_percent' => $snapshot['percent'],
            'created_at' => now(),
        ]);

        if (! $recipient->smsNotificationsEnabled() || ! AppSetting::customerWalletNegativeSmsEnabled() || blank($recipient->phone)
            || AppSetting::smsGateway() !== 'kavenegar' || AppSetting::customerWalletNegativeSmsTemplate() === '') {
            return;
        }

        try {
            app(KavenegarLookupClient::class)->sendLookup(
                $recipient->phone,
                AppSetting::customerWalletNegativeSmsTemplate(),
                KavenegarLookupClient::nameToken($recipient->first_name ?: $recipient->name),
                (string) $snapshot['balance'],
                $snapshot['percent'] === null ? null : (string) $snapshot['percent'],
            );
        } catch (Throwable $exception) {
            Log::warning('Workspace wallet SMS notification failed.', [
                'project_id' => $project->id,
                'customer_id' => $recipient->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
