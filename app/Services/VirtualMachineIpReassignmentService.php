<?php

namespace App\Services;

use App\Models\IpAddress;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VirtualMachineIpReassignmentService
{
    public function __construct(
        private readonly IpPoolService $ipPools,
        private readonly ProxmoxService $proxmox,
    ) {}

    public function reassign(VirtualMachine $vm, IpAddress $destination): VirtualMachine
    {
        $vm->loadMissing(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool']);
        $oldAddress = $vm->reservedIpAddress;

        $this->assertEligible($vm, $oldAddress);
        if ((int) $oldAddress->id === (int) $destination->id) {
            return $vm;
        }

        $destination->loadMissing('pool');
        $this->assertDestination($vm, $destination);

        $this->ipPools->reserveSpecificForVm($destination, $vm);
        $vm->refresh()->load(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool']);

        try {
            $this->sync($vm);
        } catch (Throwable $exception) {
            $compensationError = null;
            try {
                $this->ipPools->reserveSpecificForVm($oldAddress->fresh(), $vm);
                $vm->refresh()->load(['proxmoxServer', 'cloudImage', 'reservedIpAddress.pool']);
                $this->sync($vm);
            } catch (Throwable $compensationException) {
                $compensationError = $compensationException->getMessage();
                Log::critical('VM IP reassignment compensation failed', [
                    'virtual_machine_id' => $vm->id,
                    'error' => $compensationError,
                ]);
            }

            throw new RuntimeException(
                'Proxmox IP synchronization failed: '.$exception->getMessage().
                ($compensationError ? ' Rollback also failed: '.$compensationError : ''),
                previous: $exception,
            );
        }

        return $vm->refresh();
    }

    private function sync(VirtualMachine $vm): void
    {
        $server = $vm->proxmoxServer;
        $address = $vm->reservedIpAddress;
        $node = (string) $vm->node;
        $vmid = (int) $vm->vmid;
        $disabled = false;

        try {
            $this->wait($server, $node, $this->proxmox->setVmNetworkLinkState($server, $node, $vmid, false));
            $disabled = true;
            $this->wait($server, $node, $this->proxmox->configureCloudInit($server, [
                'node' => $node,
                'vmid' => $vmid,
                'cpu_cores' => $vm->cpu_cores,
                'ram_gb' => $vm->ram_gb,
                'network_bridge' => $vm->network_bridge,
                'login_username' => $vm->login_username,
                'login_password' => $vm->login_password,
                'ssh_public_key' => $vm->ssh_public_key,
                'ipconfig0' => $this->ipPools->ipConfig($address),
                'nameserver' => $this->ipPools->nameservers($address),
                'cicustom' => 'vendor=local:snippets/ubuntu-password-login.yml',
                'description' => 'Cloud-init network updated by Aviato admin panel',
            ]));
            $this->wait($server, $node, $this->proxmox->regenerateCloudInit($server, $node, $vmid));
            $antiSpoofing = $this->proxmox->applyVmIpAntiSpoofing($server, $node, $vmid, $address->address, 'net0', $vm->network_bridge ?: 'vmbr1');
            $this->wait($server, $node, $this->proxmox->setVmNetworkLinkState($server, $node, $vmid, true));
            $disabled = false;

            $vm->forceFill([
                'mac_address' => $antiSpoofing['mac_address'] ?? $vm->mac_address,
                'desired_state' => $vm->desiredStateSnapshot(),
                'remote_state' => array_merge($vm->remote_state ?? [], [
                    'cloudinit_network_updated_at' => now()->toISOString(),
                    'cloudinit_network_ip' => $vm->ip_address,
                    'anti_spoofing' => $antiSpoofing,
                ]),
            ])->save();
        } finally {
            if ($disabled) {
                try {
                    $this->wait($server, $node, $this->proxmox->setVmNetworkLinkState($server, $node, $vmid, true));
                } catch (Throwable $exception) {
                    Log::critical('VM network could not be re-enabled after IP synchronization failure', [
                        'virtual_machine_id' => $vm->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    private function wait($server, string $node, array $result): void
    {
        if (! empty($result['task_id'])) {
            $this->proxmox->waitForTask($server, $node, (string) $result['task_id'], 180);
        }
    }

    private function assertEligible(VirtualMachine $vm, ?IpAddress $oldAddress): void
    {
        if ($vm->isActionLocked()) {
            throw new RuntimeException('This VM is locked and cannot change IP.');
        }
        if (! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid || ! $oldAddress) {
            throw new RuntimeException('Only provisioned VMs with an assigned IP can change IP.');
        }
        if (! $vm->cloudImage?->cloud_init_enabled) {
            throw new RuntimeException('Cloud-init is not enabled for this VM image.');
        }
    }

    private function assertDestination(VirtualMachine $vm, IpAddress $address): void
    {
        $pool = $address->pool;
        if (! $pool?->is_active || ! in_array($address->status, [IpAddress::STATUS_AVAILABLE, IpAddress::STATUS_RELEASED], true)) {
            throw new RuntimeException('The destination IP is not available.');
        }
        if ((int) $pool->proxmox_server_id !== (int) $vm->proxmox_server_id ||
            (filled($pool->node) && filled($vm->node) && $pool->node !== $vm->node)) {
            throw new RuntimeException('The destination IP belongs to an incompatible Proxmox server or node.');
        }
    }
}
