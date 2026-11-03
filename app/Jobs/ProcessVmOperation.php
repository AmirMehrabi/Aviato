<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Models\VmOperation;
use App\Services\HetznerCloudService;
use App\Services\ProxmoxService;
use App\Services\VmOperationService;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessVmOperation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public int $operationId) {}

    public function handle(ProxmoxService $proxmox, HetznerCloudService $hetzner, VmOperationService $operations): void
    {
        $lock = Cache::lock('vm-operation-'.$this->operationId, 120);
        if (! $lock->get()) {
            return;
        }
        try {
            $operation = VmOperation::with('virtualMachine.proxmoxServer', 'virtualMachine.infrastructureLocation.hetznerAccount', 'virtualMachine.project.owner', 'virtualMachine.customer')->find($this->operationId);
            if (! $operation?->active_vm_id || $operation->action === 'upgrade') {
                return;
            }
            $vm = $operation->virtualMachine;
            $node = $operation->details['node'];
            $vmid = (int) $operation->details['vmid'];
            if ($operation->status === 'queued') {
                if ($vm->isLifecycleLocked() || $vm->provisioning_status !== VirtualMachine::PROVISION_READY) {
                    $operations->finish($operation, false);

                    return;
                }
                if ($vm->isProxmox()) {
                    $config = $proxmox->vmConfig($vm->proxmoxServer, $node, $vmid);
                    if (! empty($config['lock'])) {
                        $operations->finish($operation, false);

                        return;
                    }
                }
                if (in_array($operation->action, ['start', 'restart'], true)) {
                    $owner = $vm->project?->owner ?? $vm->customer;
                    if ($owner && ($owner->isSuspended() || app(WalletService::class)->isWalletDepleted($owner))) {
                        $operations->finish($operation, false);

                        return;
                    }
                }
                // Persist before submitting: an interrupted request must never be submitted twice.
                $operation->update(['status' => 'checking', 'started_at' => now()]);
                if ($vm->isHetzner()) {
                    $account = $vm->infrastructureLocation->hetznerAccount;
                    $result = match ($operation->action) {
                        'start' => $hetzner->powerOn($account, $vm->remote_id),
                        'restart' => $hetzner->reboot($account, $vm->remote_id),
                        'shutdown' => $hetzner->shutdown($account, $vm->remote_id),
                        'force-stop' => $hetzner->powerOff($account, $vm->remote_id),
                    };
                    $task = data_get($result, 'action.id');
                } else {
                    $result = match ($operation->action) {
                        'start' => $proxmox->startVm($vm->proxmoxServer, $node, $vmid),
                        'restart' => $proxmox->rebootVm($vm->proxmoxServer, $node, $vmid),
                        'shutdown' => $proxmox->shutdownVm($vm->proxmoxServer, $node, $vmid, forceStopFallback: false, forceStopOnTimeout: false),
                        'force-stop' => $proxmox->stopVm($vm->proxmoxServer, $node, $vmid),
                    };
                    $task = $result['task_id'] ?? null;
                }
                $operation->update(['task_id' => $task, 'status' => $task ? 'running' : 'checking']);
            }
            if (! $operation->task_id) {
                return; // Unknown submission result stays locked for support to investigate.
            }
            if ($vm->isHetzner()) {
                $task = $hetzner->actionStatus($vm->infrastructureLocation->hetznerAccount, $operation->task_id);
                if (($task['status'] ?? null) === 'error') {
                    $operations->finish($operation, false);

                    return;
                }
                if (($task['status'] ?? null) !== 'success') {
                    return;
                }
                $remote = $hetzner->server($vm->infrastructureLocation->hetznerAccount, $vm->remote_id);
                $state = $remote['status'] ?? null;
                $expected = in_array($operation->action, ['start', 'restart'], true) ? 'running' : 'off';
                $config = [];
            } else {
                $task = $proxmox->taskStatus($vm->proxmoxServer, $node, $operation->task_id);
                if (($task['status'] ?? null) !== 'stopped') {
                    return;
                }
                $exit = trim((string) ($task['exitstatus'] ?? ''));
                if ($exit !== 'OK' && ! preg_match('/^WARNINGS: \d+$/', $exit)) {
                    $operations->finish($operation, false);

                    return;
                }
                $remote = $proxmox->vmStatus($vm->proxmoxServer, $node, $vmid);
                $state = $remote['status'] ?? null;
                $expected = in_array($operation->action, ['start', 'restart'], true) ? 'running' : 'stopped';
                $config = $proxmox->vmConfig($vm->proxmoxServer, $node, $vmid);
            }
            if ($state === $expected && empty($config['lock'])) {
                $operations->finish($operation, true);
            } else {
                $operation->update(['status' => 'checking']);
            }
        } catch (Throwable $exception) {
            report($exception);
            if (isset($operation) && $operation?->active_vm_id) {
                $operation->update(['status' => 'checking']);
            }
        } finally {
            $lock->release();
        }
    }
}
