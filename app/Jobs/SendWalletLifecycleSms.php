<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\VirtualMachine;
use App\Services\Sms\KavenegarLookupClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWalletLifecycleSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $customerId, public string $template, public string $serverName, public int $vmId, public string $event, public string $cycle) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(KavenegarLookupClient $sms): void
    {
        $vm = VirtualMachine::find($this->vmId);
        if (! $vm || $vm->isDeleted()) {
            return;
        }
        $state = $vm->wallet_restriction ?? [];
        if ($this->event === 'restored' ? $state !== [] : data_get($state, 'created_at') !== $this->cycle) {
            return;
        }
        $customer = Customer::find($this->customerId);
        if (! $customer || $customer->status !== Customer::STATUS_ACTIVE || ! $customer->smsNotificationsEnabled()
            || blank($customer->phone) || ! AppSetting::customerWalletNegativeSmsEnabled() || AppSetting::smsGateway() !== 'kavenegar') {
            return;
        }
        $name = preg_replace('/[^\p{L}\p{N}]/u', '', KavenegarLookupClient::nameToken($customer->first_name ?: $customer->name)) ?: 'مشتری';
        $server = preg_replace('/[^\p{L}\p{N}]/u', '', $this->serverName) ?: 'سرور';
        $sms->sendLookup($customer->phone, $this->template, mb_substr($name, 0, 100), token10: mb_substr($server, 0, 100));
    }
}
