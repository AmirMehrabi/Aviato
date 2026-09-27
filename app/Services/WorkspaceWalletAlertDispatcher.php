<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkspaceWalletAlertDispatcher
{
    public function __construct(
        private readonly WorkspaceWalletAlertService $alerts,
        private readonly WorkspaceWalletQuietHours $quietHours,
    ) {}

    public function dispatchPending(): int
    {
        if ($this->quietHours->isQuiet()) {
            return 0;
        }

        $sent = 0;
        $customerIds = DB::table('workspace_wallet_alert_queue')
            ->where('status', 'pending')->distinct()->limit(500)->pluck('customer_id');

        foreach ($customerIds as $customerId) {
            $sent += $this->dispatchForRecipient((int) $customerId) ? 1 : 0;
        }

        $smsIds = DB::table('workspace_wallet_alert_queue')->where('status', 'sent')
            ->where('sms_status', 'pending')->orderBy('id')->limit(500)->pluck('id');
        foreach ($smsIds as $smsId) {
            $this->dispatchSms((int) $smsId);
        }

        return $sent;
    }

    public function dispatchForRecipient(int $customerId): bool
    {
        if ($this->quietHours->isQuiet()) {
            return false;
        }

        $candidate = DB::table('workspace_wallet_alert_queue')
            ->where('customer_id', $customerId)->where('status', 'pending')
            ->orderBy('id')->first();

        if (! $candidate) {
            return false;
        }

        if ($candidate->last_attempt_at && Carbon::parse($candidate->last_attempt_at)->gt(now()->subMinutes(5))) {
            return false;
        }

        try {
            $sent = DB::transaction(function () use ($candidate, $customerId): bool {
                $entry = DB::table('workspace_wallet_alert_queue')->where('id', $candidate->id)->lockForUpdate()->first();
                if (! $entry || $entry->status !== 'pending' || $this->quietHours->isQuiet()) {
                    return false;
                }

                if (DB::table('workspace_wallet_alert_queue')->where('customer_id', $customerId)
                    ->where('status', 'pending')->where('id', '<', $entry->id)->exists()) {
                    return false;
                }

                if ($entry->is_deferred && DB::table('workspace_wallet_alert_queue')
                    ->where('customer_id', $customerId)->where('status', 'sent')
                    ->where('delivered_at', '>', now()->subHour())->exists()) {
                    return false;
                }

                $project = Project::query()->with('owner')->find($entry->project_id);
                $recipient = Customer::query()->find($customerId);
                if (! $project || ! $recipient || ! collect($this->alerts->recipients($project))->contains(fn (Customer $item): bool => $item->id === $recipient->id)) {
                    $this->cancel($entry->id);

                    return false;
                }

                $snapshot = $this->alerts->snapshot($project->owner);
                if ($snapshot['monthly_cost'] <= 0) {
                    $this->cancel($entry->id);

                    return false;
                }

                $thresholds = DB::table('workspace_wallet_alert_states')
                    ->where('project_id', $project->id)->where('customer_id', $customerId)
                    ->whereIn('threshold_percent', $this->alerts->thresholds($project))
                    ->pluck('threshold_percent')
                    ->filter(fn ($threshold): bool => (int) $threshold * $snapshot['monthly_cost'] >= $snapshot['balance'] * 100);

                if ($thresholds->isEmpty()) {
                    $this->cancel($entry->id);

                    return false;
                }

                $this->alerts->deliverQueued($project, $recipient, $snapshot, (int) $thresholds->min());
                DB::table('workspace_wallet_alert_queue')->where('id', $entry->id)->update([
                    'status' => 'sent',
                    'delivered_at' => now(),
                    'threshold_percent' => (int) $thresholds->min(),
                    'sms_status' => $this->alerts->smsEnabled($recipient) ? 'pending' : 'skipped',
                    'last_error' => null,
                ]);

                return true;
            });

            if ($sent) {
                $this->dispatchSms((int) $candidate->id);
            }

            return $sent;
        } catch (Throwable $exception) {
            DB::table('workspace_wallet_alert_queue')->where('id', $candidate->id)->where('status', 'pending')->update([
                'attempts' => DB::raw('attempts + 1'),
                'last_attempt_at' => now(),
                'last_error' => $exception->getMessage(),
            ]);
            Log::warning('Workspace wallet alert delivery failed.', [
                'queue_id' => $candidate->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function cancel(int $id): void
    {
        DB::table('workspace_wallet_alert_queue')->where('id', $id)->update(['status' => 'canceled']);
    }

    private function dispatchSms(int $id): void
    {
        if ($this->quietHours->isQuiet()) {
            return;
        }

        try {
            DB::transaction(function () use ($id): void {
                $entry = DB::table('workspace_wallet_alert_queue')->where('id', $id)->lockForUpdate()->first();
                if (! $entry || $entry->status !== 'sent' || $entry->sms_status !== 'pending' || $this->quietHours->isQuiet()) {
                    return;
                }

                if ($entry->last_sms_attempt_at && Carbon::parse($entry->last_sms_attempt_at)->gt(now()->subMinutes(5))) {
                    return;
                }

                $project = Project::query()->with('owner')->find($entry->project_id);
                $recipient = Customer::query()->find($entry->customer_id);
                if (! $project || ! $recipient || ! $this->alerts->smsEnabled($recipient)
                    || ! collect($this->alerts->recipients($project))->contains(fn (Customer $item): bool => $item->id === $recipient->id)) {
                    DB::table('workspace_wallet_alert_queue')->where('id', $id)->update(['sms_status' => 'skipped']);

                    return;
                }

                $snapshot = $this->alerts->snapshot($project->owner);
                if ($snapshot['monthly_cost'] <= 0 || (int) $entry->threshold_percent * $snapshot['monthly_cost'] < $snapshot['balance'] * 100) {
                    DB::table('workspace_wallet_alert_queue')->where('id', $id)->update(['sms_status' => 'skipped']);

                    return;
                }

                $sent = $this->alerts->sendSmsNow($project, $recipient, $snapshot);
                DB::table('workspace_wallet_alert_queue')->where('id', $id)->update([
                    'sms_status' => $sent ? 'sent' : 'pending',
                    'sms_attempts' => DB::raw('sms_attempts + 1'),
                    'last_sms_attempt_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            DB::table('workspace_wallet_alert_queue')->where('id', $id)->where('sms_status', 'pending')->update([
                'sms_attempts' => DB::raw('sms_attempts + 1'),
                'last_sms_attempt_at' => now(),
            ]);
            Log::warning('Workspace wallet queued SMS delivery failed.', [
                'queue_id' => $id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
