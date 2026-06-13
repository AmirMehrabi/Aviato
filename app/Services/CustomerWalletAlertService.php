<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\VirtualMachine;
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
        $customer->loadMissing('virtualMachines.proxmoxServer');
        $wallet = Wallet::query()->where('customer_id', $customer->id)->first();

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

            $this->restoreLockedVirtualMachines($customer);

            return;
        }

        $wallet->forceFill([
            'negative_notification_count' => (int) ($wallet->negative_notification_count ?? 0),
        ])->save();

        $this->lockVirtualMachines($customer);

        if (! $customer->smsNotificationsEnabled() || ! AppSetting::customerWalletNegativeSmsEnabled() || blank($customer->phone)) {
            return;
        }

        DB::transaction(function () use ($customer, $wallet, $threshold): void {
            $lockedWallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->first();

            if (! $lockedWallet || $lockedWallet->balance >= $threshold) {
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
        });
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

    private function lockVirtualMachines(Customer $customer): void
    {
        $customer->virtualMachines()
            ->with('proxmoxServer')
            ->whereNotNull('node')
            ->whereNotNull('vmid')
            ->whereNotIn('status', [VirtualMachine::STATUS_DELETING, VirtualMachine::STATUS_DELETED, VirtualMachine::STATUS_SUSPENDED])
            ->get()
            ->each(function ($vm) use ($customer): void {
                if ($vm->proxmoxServer && $vm->status === VirtualMachine::STATUS_RUNNING) {
                    try {
                        $this->proxmox->stopVm($vm->proxmoxServer, (string) $vm->node, (int) $vm->vmid);
                    } catch (Throwable $exception) {
                        Log::warning('Failed to stop VM after customer wallet lock.', [
                            'customer_id' => $customer->id,
                            'vm_id' => $vm->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                $vm->forceFill([
                    'status' => VirtualMachine::STATUS_SUSPENDED,
                    'desired_state' => array_merge($vm->desired_state ?? [], [
                        'status' => VirtualMachine::STATUS_STOPPED,
                        'wallet_locked_at' => now()->toISOString(),
                    ]),
                    'remote_state' => array_merge($vm->remote_state ?? [], [
                        'wallet_locked_at' => now()->toISOString(),
                        'wallet_unlocked_at' => null,
                    ]),
                ])->save();
            });
    }

    private function restoreLockedVirtualMachines(Customer $customer): void
    {
        $customer->virtualMachines()
            ->where('status', VirtualMachine::STATUS_SUSPENDED)
            ->get()
            ->each(function ($vm): void {
                $vm->forceFill([
                    'status' => VirtualMachine::STATUS_STOPPED,
                    'desired_state' => array_merge($vm->desired_state ?? [], [
                        'status' => VirtualMachine::STATUS_STOPPED,
                    ]),
                    'remote_state' => array_merge($vm->remote_state ?? [], [
                        'wallet_locked_at' => null,
                        'wallet_unlocked_at' => now()->toISOString(),
                    ]),
                ])->save();
            });
    }
}
