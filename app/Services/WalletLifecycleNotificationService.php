<?php

namespace App\Services;

use App\Jobs\SendWalletLifecycleSms;
use App\Models\AppSetting;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\DB;

class WalletLifecycleNotificationService
{
    public function notify(VirtualMachine $vm, string $event): void
    {
        $template = (string) AppSetting::getValue('customer.wallet.'.$event.'_sms_template', '');
        // New notifications remain disabled until an approved template is selected.
        if ($template === '' || ! $vm->project) {
            return;
        }
        DB::transaction(function () use ($vm, $event, $template): void {
            $locked = VirtualMachine::whereKey($vm->id)->lockForUpdate()->firstOrFail();
            $state = $locked->wallet_restriction ?? [];
            if (isset($state['sms_notices'][$event])) {
                return;
            }
            foreach (app(WorkspaceWalletAlertService::class)->recipients($vm->project) as $recipient) {
                SendWalletLifecycleSms::dispatch($recipient->id, $template, $vm->display_name, $vm->id, $event, (string) ($state['created_at'] ?? ''))->afterCommit();
            }
            $state['sms_notices'][$event] = now()->toISOString();
            $locked->forceFill(['wallet_restriction' => $state])->save();
        });
        $vm->refresh();
    }
}
