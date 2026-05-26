<?php

namespace App\Jobs;

use App\Models\VirtualMachine;
use App\Services\IpPoolService;
use App\Services\ProxmoxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionCloudVirtualMachine implements ShouldQueue
{
    use FoundationQueueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly int $virtualMachineId,
        public readonly array $options = [],
    ) {}

    public function handle(ProxmoxService $proxmox, IpPoolService $ipPools): void
    {
        $vm = VirtualMachine::query()
            ->with(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool'])
            ->findOrFail($this->virtualMachineId);

        $server = $vm->proxmoxServer;
        $image = $vm->cloudImage;
        $address = $vm->reservedIpAddress;

        if (! $server || ! $image || ! $address) {
            throw new \RuntimeException('Provisioning cannot continue without Proxmox server, cloud image, and reserved IP.');
        }

        $history = [];
        $remoteCreated = false;

        try {
            $next = $proxmox->nextVmid($server);
            $vmid = (int) $next['vmid'];

            $vm->forceFill([
                'vmid' => $vmid,
                'provisioning_task_id' => null,
                'remote_state' => ['steps' => $history],
            ])->save();

            $clone = $proxmox->cloneCloudTemplate($server, [
                'node' => $vm->node,
                'template_vmid' => $vm->template_vmid,
                'newid' => $vmid,
                'name' => $vm->name,
                'storage' => $vm->storage,
                'description' => 'Created from Aviato panel for customer #'.$vm->customer_id,
            ]);
            $history[] = ['step' => 'clone', 'result' => $clone];
            $vm->forceFill(['provisioning_task_id' => $clone['task_id'] ?? null, 'remote_state' => ['steps' => $history]])->save();
            if (! empty($clone['task_id'])) {
                $history[] = ['step' => 'clone_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $clone['task_id'])];
            }
            $remoteCreated = true;

            $config = $proxmox->configureCloudInit($server, [
                'node' => $vm->node,
                'vmid' => $vmid,
                'cpu_cores' => $vm->cpu_cores,
                'ram_gb' => $vm->ram_gb,
                'login_username' => $vm->login_username,
                'login_password' => $vm->login_password,
                'ssh_public_key' => $vm->ssh_public_key,
                'ipconfig0' => 'ip=dhcp',
                'nameserver' => $ipPools->nameservers($address),
                'cicustom' => 'vendor=local:snippets/ubuntu-password-login.yml',
                'onboot' => $this->options['onboot'] ?? false,
                'description' => 'Cloud-init configured by Aviato panel',
            ]);
            $history[] = ['step' => 'config', 'result' => $config];

            $resize = $proxmox->resizeDisk($server, $vm->node, $vmid, $image->disk_device, $vm->disk_gb);
            $history[] = ['step' => 'resize', 'result' => $resize];
            if (! empty($resize['task_id'])) {
                $history[] = ['step' => 'resize_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $resize['task_id'])];
            }

            $cloudInit = $proxmox->regenerateCloudInit($server, $vm->node, $vmid);
            $history[] = ['step' => 'cloudinit_regenerate', 'result' => $cloudInit];
            if (! empty($cloudInit['task_id'])) {
                $history[] = ['step' => 'cloudinit_regenerate_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $cloudInit['task_id'])];
            }

            if ($this->options['start_after_create'] ?? true) {
                $start = $proxmox->startVm($server, $vm->node, $vmid);
                $history[] = ['step' => 'start', 'result' => $start];
                if (! empty($start['task_id'])) {
                    $history[] = ['step' => 'start_wait', 'result' => $proxmox->waitForTask($server, $vm->node, $start['task_id'])];
                }
            }

            $history[] = ['step' => 'config_verify', 'result' => $proxmox->vmConfig($server, $vm->node, $vmid)];

            $ipPools->assign($address, $vm);

            $vm->forceFill([
                'status' => ($this->options['start_after_create'] ?? true) ? VirtualMachine::STATUS_RUNNING : VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_READY,
                'last_started_at' => ($this->options['start_after_create'] ?? true) ? now() : null,
                'last_billed_at' => now(),
                'last_seen_at' => now(),
                'remote_state' => ['steps' => $history, 'finished_at' => now()->toISOString()],
            ])->save();
        } catch (Throwable $exception) {
            Log::error('Cloud VM provisioning failed', [
                'virtual_machine_id' => $vm->id,
                'vmid' => $vm->vmid,
                'message' => $exception->getMessage(),
            ]);

            if (! $remoteCreated && $address) {
                $ipPools->release($address);
            }

            $vm->forceFill([
                'status' => VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_FAILED,
                'remote_state' => [
                    'steps' => $history,
                    'error' => $exception->getMessage(),
                    'failed_at' => now()->toISOString(),
                ],
            ])->save();

            throw $exception;
        }
    }
}
