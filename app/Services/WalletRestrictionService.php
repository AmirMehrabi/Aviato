<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\VirtualMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class WalletRestrictionService
{
    public function __construct(
        private readonly WorkspaceWalletAlertService $walletAlerts,
        private readonly WalletNetworkRestrictionService $networks,
        private readonly ProxmoxService $proxmox,
        private readonly HetznerCloudService $hetzner,
        private readonly UsageBalanceService $balances,
    ) {}

    public function reconcile(Customer $owner): void
    {
        $snapshot = $this->walletAlerts->snapshot($owner);
        $shutdownAt = -max(1, (int) ceil($snapshot['monthly_cost'] * AppSetting::customerWalletShutdownPercentage() / 100));
        VirtualMachine::query()
            ->notDeleted()
            ->where(function (Builder $query) use ($owner): void {
                $query->whereHas('project', fn (Builder $project) => $project->where('owner_customer_id', $owner->id))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('project_id')->where('customer_id', $owner->id));
            })
            ->with(['proxmoxServer', 'infrastructureLocation.hetznerAccount'])
            ->orderBy('id')
            ->chunkById(100, function ($machines) use ($owner, $shutdownAt): void {
                foreach ($machines as $vm) {
                    $owner->refresh();
                    $balance = $this->balances->effectiveBalance($owner);
                    $target = ! $owner->auto_suspend_vms || $balance > 0
                        ? 'available'
                        : ($balance <= $shutdownAt ? 'stopped' : 'frozen');
                    try {
                        $this->reconcileVm($owner, $vm, $target);
                    } catch (Throwable $exception) {
                        $state = $vm->fresh()?->wallet_restriction ?? [];
                        $vm->forceFill(['wallet_restriction' => array_merge($state, [
                            'last_error' => $exception->getMessage(),
                            'last_error_at' => now()->toISOString(),
                        ])])->save();
                        Log::error('Wallet VM restriction reconciliation failed', [
                            'customer_id' => $owner->id,
                            'virtual_machine_id' => $vm->id,
                            'target' => $target,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function reconcileVm(Customer $owner, VirtualMachine $vm, string $target): void
    {
        if ($vm->isActionLocked() || $vm->provisioning_status !== VirtualMachine::PROVISION_READY) {
            return;
        }

        if ($vm->status === VirtualMachine::STATUS_SUSPENDED && ! filled(data_get($vm->remote_state, 'wallet_locked_at'))) {
            return;
        }

        $state = $vm->wallet_restriction ?? [];
        if ($vm->status === VirtualMachine::STATUS_SUSPENDED && filled(data_get($vm->remote_state, 'wallet_locked_at'))) {
            // Legacy wallet locks did not record whether the machine was running.
            $vm->forceFill(['status' => VirtualMachine::STATUS_STOPPED])->save();
            $state += ['wallet_stopped' => true, 'resume_on_funding' => false];
            $this->saveState($vm, $state);
        }

        if ($target === 'available') {
            if ($state === []) {
                return;
            }
            if ($owner->isSuspended()) {
                return;
            }
            $this->networks->restore($vm, $state);
            if (! empty($state['wallet_stopped']) && ! empty($state['resume_on_funding'])) {
                $this->powerOn($vm);
                $vm->forceFill(['status' => VirtualMachine::STATUS_RUNNING])->save();
            }
            $vm->forceFill([
                'wallet_restriction' => null,
                'remote_state' => array_merge($vm->remote_state ?? [], ['wallet_locked_at' => null, 'wallet_unlocked_at' => now()->toISOString()]),
            ])->save();

            return;
        }

        if ($state === []) {
            $state = [
                'resume_on_funding' => $vm->isRunning(),
                'wallet_stopped' => false,
                'created_at' => now()->toISOString(),
            ];
            $this->saveState($vm, $state);
        }

        // Network isolation is idempotent and records the original interfaces before modifying them.
        try {
            $this->networks->freeze($vm, $state);
        } catch (Throwable $exception) {
            if ($target !== 'stopped') {
                throw $exception;
            }
            Log::warning('Network freeze failed before wallet debt shutdown', [
                'virtual_machine_id' => $vm->id,
                'error' => $exception->getMessage(),
            ]);
            $vm->refresh();
            $this->saveState($vm, array_merge($vm->wallet_restriction ?? [], [
                'last_error' => $exception->getMessage(),
                'last_error_at' => now()->toISOString(),
            ]));
        }
        $state = $vm->fresh()->wallet_restriction ?? $state;

        if ($target === 'stopped' && $vm->isRunning()) {
            $this->powerOff($vm);
            $state['wallet_stopped'] = true;
            $state['stopped_at'] = now()->toISOString();
            $vm->forceFill([
                'status' => VirtualMachine::STATUS_STOPPED,
                'wallet_restriction' => $state,
            ])->save();
        }
    }

    private function powerOff(VirtualMachine $vm): void
    {
        if ($vm->isHetzner()) {
            $account = $vm->infrastructureLocation?->hetznerAccount;
            if (! $account || ! $vm->remote_id) {
                throw new \RuntimeException('Hetzner server is missing provider credentials or ID.');
            }
            try {
                $action = $this->hetzner->shutdown($account, $vm->remote_id);
                $actionId = data_get($action, 'action.id');
                if (! $actionId) {
                    throw new \RuntimeException('Hetzner did not return a shutdown action ID.');
                }
                $this->hetzner->waitForAction($account, $actionId, 180);
            } catch (Throwable $exception) {
                Log::warning('Hetzner graceful wallet shutdown failed; requesting power off', [
                    'virtual_machine_id' => $vm->id,
                    'error' => $exception->getMessage(),
                ]);
                $action = $this->hetzner->powerOff($account, $vm->remote_id);
                $actionId = data_get($action, 'action.id');
                if (! $actionId) {
                    throw new \RuntimeException('Hetzner did not return a power-off action ID.');
                }
                $this->hetzner->waitForAction($account, $actionId, 180);
            }
            if (($this->hetzner->server($account, $vm->remote_id)['status'] ?? null) !== 'off') {
                throw new \RuntimeException('Hetzner did not confirm server shutdown.');
            }

            return;
        }

        $this->assertProxmox($vm);
        $action = $vm->isLxc()
            ? $this->proxmox->shutdownLxc($vm->proxmoxServer, $vm->node, $vm->vmid)
            : $this->proxmox->shutdownVm($vm->proxmoxServer, $vm->node, $vm->vmid, true, ['source' => 'wallet_shutdown', 'virtual_machine_id' => $vm->id]);
        if (filled($action['task_id'] ?? null)) {
            $this->proxmox->waitForTask($vm->proxmoxServer, $vm->node, $action['task_id'], 180);
        }
        $stopped = $vm->isLxc()
            ? $this->proxmox->lxcStatus($vm->proxmoxServer, $vm->node, $vm->vmid)
            : $this->proxmox->waitForVmStopped($vm->proxmoxServer, $vm->node, $vm->vmid, 60);
        if (($stopped['status'] ?? null) !== 'stopped') {
            throw new \RuntimeException('Proxmox did not confirm VM shutdown.');
        }
    }

    private function powerOn(VirtualMachine $vm): void
    {
        if ($vm->isHetzner()) {
            $account = $vm->infrastructureLocation?->hetznerAccount;
            if (! $account || ! $vm->remote_id) {
                throw new \RuntimeException('Hetzner server is missing provider credentials or ID.');
            }
            $action = $this->hetzner->powerOn($account, $vm->remote_id);
            $actionId = data_get($action, 'action.id');
            if (! $actionId) {
                throw new \RuntimeException('Hetzner did not return a power-on action ID.');
            }
            $this->hetzner->waitForAction($account, $actionId, 180);
            if (($this->hetzner->server($account, $vm->remote_id)['status'] ?? null) !== 'running') {
                throw new \RuntimeException('Hetzner did not confirm server start.');
            }

            return;
        }

        $this->assertProxmox($vm);
        $action = $vm->isLxc()
            ? $this->proxmox->startLxc($vm->proxmoxServer, $vm->node, $vm->vmid)
            : $this->proxmox->startVm($vm->proxmoxServer, $vm->node, $vm->vmid, ['source' => 'wallet_restore', 'virtual_machine_id' => $vm->id]);
        if (filled($action['task_id'] ?? null)) {
            $this->proxmox->waitForTask($vm->proxmoxServer, $vm->node, $action['task_id'], 180);
        }
        $status = $vm->isLxc()
            ? $this->proxmox->lxcStatus($vm->proxmoxServer, $vm->node, $vm->vmid)
            : $this->proxmox->vmStatus($vm->proxmoxServer, $vm->node, $vm->vmid);
        if (($status['status'] ?? null) !== 'running') {
            throw new \RuntimeException('Proxmox did not confirm VM start.');
        }
    }

    private function assertProxmox(VirtualMachine $vm): void
    {
        if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
            throw new \RuntimeException('Proxmox guest details are missing.');
        }
    }

    private function saveState(VirtualMachine $vm, array $state): void
    {
        $vm->forceFill(['wallet_restriction' => $state])->save();
    }
}
