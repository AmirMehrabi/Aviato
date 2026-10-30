<?php

namespace App\Services\Notifications;

use App\Jobs\SendMachineCreatedSms;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\Log;
use Throwable;

class MachineCreatedNotificationService
{
    public function send(VirtualMachine $vm): void
    {
        $creator = $vm->creator;
        if (! $creator) {
            return;
        }

        $variables = ['server_name' => $vm->display_name, 'machine_id' => (string) $vm->id, 'creator_name' => $creator->name];
        $this->deliver($creator, 'vm_created_customer', $variables, route('customer.servers.show', $vm));
        User::query()->where('is_active', true)->each(function (User $admin) use ($vm, $variables): void {
            if ($admin->allows('virtual-machines.read')) {
                $this->deliver($admin, 'vm_created_admin', $variables, route('admin.virtual-machines.show', $vm));
            }
        });
    }

    private function deliver(object $recipient, string $event, array $variables, string $url): void
    {
        app(NotificationEventSubscriber::class)->notify($recipient, $event, $variables, $url);
        if (blank($recipient->phone) || AppSetting::smsGateway() !== 'kavenegar'
            || ! app(NotificationTemplates::class)->enabled($event, 'sms', $recipient)) {
            return;
        }
        try {
            SendMachineCreatedSms::dispatch($recipient->getMorphClass(), $recipient->getKey(), $event, $variables['machine_id'])
                ->onConnection(EmailQueue::connection())->onQueue('mail')->afterCommit();
        } catch (Throwable) {
            Log::warning('Unable to queue machine creation SMS.', ['event' => $event]);
        }
    }
}
