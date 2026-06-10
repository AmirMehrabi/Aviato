<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Wallet;
use App\Services\Sms\KavenegarLookupClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerWalletAlertService
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
    ) {}

    public function handleWalletBalanceChange(Customer $customer): void
    {
        $customer->loadMissing('virtualMachines.proxmoxServer', 'wallet');
        $wallet = $customer->wallet;

        if (! $wallet instanceof Wallet) {
            return;
        }

        $threshold = AppSetting::customerWalletNegativeThreshold();
        if ($wallet->balance >= $threshold) {
            if ((int) $wallet->negative_notification_count !== 0) {
                $wallet->forceFill([
                    'negative_notification_count' => 0,
                    'negative_notified_at' => null,
                ])->save();
            }

            return;
        }

        if ($customer->isSuspended()) {
            return;
        }

        $wallet->forceFill([
            'negative_notification_count' => (int) ($wallet->negative_notification_count ?? 0),
        ])->save();

        if (! $customer->smsNotificationsEnabled() || ! AppSetting::customerWalletNegativeSmsEnabled() || blank($customer->phone)) {
            return;
        }

        $sent = false;

        DB::transaction(function () use ($customer, $wallet, $threshold, &$sent): void {
            $lockedWallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->first();

            if (! $lockedWallet || $lockedWallet->balance >= $threshold || $customer->fresh()->isSuspended()) {
                return;
            }

            $count = (int) ($lockedWallet->negative_notification_count ?? 0);
            if ($count >= 3) {
                return;
            }

            $this->sendSms($customer);

            $count++;
            $lockedWallet->forceFill([
                'negative_notification_count' => $count,
                'negative_notified_at' => now(),
            ])->save();

            $sent = true;

            if ($count >= 3) {
                $this->suspendAndDisable($customer);
            }
        });

        if ($sent) {
            return;
        }
    }

    private function sendSms(Customer $customer): void
    {
        if (AppSetting::smsGateway() !== 'kavenegar') {
            return;
        }

        $template = AppSetting::customerWalletNegativeSmsTemplate();
        if ($template === '') {
            return;
        }

        try {
            app(KavenegarLookupClient::class)->sendLookup(
                $customer->phone,
                $template,
                $customer->first_name !== '' ? $customer->first_name : $customer->name,
            );
        } catch (Throwable $exception) {
            Log::warning('Customer wallet negative SMS notification failed.', [
                'customer_id' => $customer->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function suspendAndDisable(Customer $customer): void
    {
        $customer->suspend('کیف پول مشتری پس از 3 نوبت هشدار منفی به‌صورت خودکار تعلیق شد.');

        $customer->virtualMachines()
            ->with('proxmoxServer')
            ->whereNotNull('node')
            ->whereNotNull('vmid')
            ->get()
            ->each(function ($vm) use ($customer): void {
                if ($vm->proxmoxServer && $vm->status !== 'stopped') {
                    try {
                        $this->proxmox->stopVm($vm->proxmoxServer, (string) $vm->node, (int) $vm->vmid);
                    } catch (Throwable $exception) {
                        Log::warning('Failed to stop VM after customer wallet suspension.', [
                            'customer_id' => $customer->id,
                            'vm_id' => $vm->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                $vm->forceFill([
                    'status' => 'stopped',
                ])->save();
            });
    }
}
