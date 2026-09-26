<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\VirtualMachine;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerWalletAlertService
{
    public function __construct(
        private readonly ProxmoxService $proxmox,
        private readonly UsageBalanceService $usageBalances,
        private readonly WorkspaceWalletAlertService $workspaceAlerts,
    ) {}

    public function handleWalletBalanceChange(Customer $customer): void
    {
        $wallet = Wallet::query()->where('customer_id', $customer->id)->first();

        if (! $wallet instanceof Wallet) {
            return;
        }

        $effectiveBalance = $this->usageBalances->effectiveBalance($customer);

        if ($effectiveBalance <= 0 && $customer->auto_suspend_vms) {
            $this->lockVirtualMachines($customer);
        } elseif ($effectiveBalance > 0) {
            $this->restoreLockedVirtualMachines($customer);
        }

        $this->workspaceAlerts->checkOwner($customer);
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
                        $this->proxmox->stopVm(
                            $vm->proxmoxServer,
                            (string) $vm->node,
                            (int) $vm->vmid,
                            [
                                'source' => 'wallet_suspension',
                                'virtual_machine_id' => $vm->id,
                                'customer_id' => $customer->id,
                            ],
                        );
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
            ->filter(fn ($vm): bool => filled(data_get($vm->remote_state, 'wallet_locked_at')))
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
