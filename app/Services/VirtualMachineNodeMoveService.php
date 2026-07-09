<?php

namespace App\Services;

use App\Models\CloudImageNodeMapping;
use App\Models\ProxmoxServer;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VirtualMachineNodeMoveService
{
    public function __construct(private readonly ProxmoxService $proxmox) {}

    public function move(VirtualMachine $vm, string $targetNode, string $mode, bool $online, mixed $adminId): string
    {
        $vm->loadMissing('proxmoxServer');

        if ($vm->isActionLocked()) {
            throw new RuntimeException('VM is deleting or deleted.');
        }

        if (! $vm->isProxmox() || ! $vm->proxmoxServer || ! $vm->node || ! $vm->vmid) {
            throw new RuntimeException('VM is missing Proxmox server, source node, or VMID.');
        }

        if ((string) $vm->node === $targetNode) {
            throw new RuntimeException('VM is already assigned to '.$targetNode.'.');
        }

        $server = $vm->proxmoxServer;
        $activeNodes = collect(data_get($server->remote_inventory, 'nodes', []))
            ->map(fn (array $node): ?string => $node['node'] ?? $node['name'] ?? null)
            ->filter()
            ->values();

        if (! $activeNodes->contains($targetNode)) {
            throw new RuntimeException('Target node '.$targetNode.' is not present in the latest synced Proxmox inventory.');
        }

        $sourceNode = (string) $vm->node;
        $vmid = (int) $vm->vmid;
        $targetStatus = $this->vmStatusForNodeMove($server, $targetNode, $vmid, $vm);
        $sourceStatus = $this->vmStatusForNodeMove($server, $sourceNode, $vmid, $vm);

        if ($sourceStatus && $targetStatus) {
            throw new RuntimeException('VMID exists on both source and target nodes; resolve duplicate remote state first.');
        }

        if (! $sourceStatus && ! $targetStatus) {
            throw new RuntimeException('VMID was not found on source or target node.');
        }

        $migration = null;
        $action = 'reconciled';

        if ($targetStatus) {
            if ($mode !== 'reconcile_only') {
                throw new RuntimeException('VM already exists on target; use reconcile-only mode.');
            }
        } else {
            if ($mode !== 'migrate') {
                throw new RuntimeException('VM is still on source; use migrate mode to move it in Proxmox first.');
            }

            $migration = $this->proxmox->migrateVm($server, $sourceNode, $targetNode, $vmid, $online, [
                'source' => 'admin_bulk_node_move',
                'virtual_machine_id' => $vm->id,
                'admin_id' => $adminId,
            ]);

            if (! empty($migration['task_id'])) {
                $this->proxmox->waitForTask($server, $sourceNode, (string) $migration['task_id'], 900);
            }

            $targetStatus = $this->vmStatusForNodeMove($server, $targetNode, $vmid, $vm);

            if (! $targetStatus) {
                throw new RuntimeException('Proxmox migration finished but VM was not found on target node.');
            }

            $action = 'migrated';
        }

        $targetConfig = $this->proxmox->vmConfigOrNull($server, $targetNode, $vmid);
        $mappingId = $this->nodeMappingIdFor($vm, $targetNode);

        $vm->forceFill([
            'node' => $targetNode,
            'cloud_image_node_mapping_id' => $mappingId ?? $vm->cloud_image_node_mapping_id,
            'status' => ($targetStatus['status'] ?? null) === 'running'
                ? VirtualMachine::STATUS_RUNNING
                : (($targetStatus['status'] ?? null) === 'stopped' ? VirtualMachine::STATUS_STOPPED : $vm->status),
            'last_seen_at' => now(),
            'remote_state' => array_merge($vm->remote_state ?? [], [
                'node_move' => [
                    'action' => $action,
                    'from_node' => $sourceNode,
                    'to_node' => $targetNode,
                    'task_id' => $migration['task_id'] ?? null,
                    'admin_id' => $adminId,
                    'moved_at' => now()->toISOString(),
                    'target_status' => $targetStatus,
                    'target_config' => $targetConfig,
                ],
            ]),
        ]);
        $vm->desired_state = $vm->desiredStateSnapshot();
        $vm->save();

        return $action;
    }

    private function nodeMappingIdFor(VirtualMachine $vm, string $targetNode): ?int
    {
        if (! $vm->cloud_image_id || ! $vm->proxmox_server_id) {
            return null;
        }

        return CloudImageNodeMapping::query()
            ->where('cloud_image_id', $vm->cloud_image_id)
            ->where('proxmox_server_id', $vm->proxmox_server_id)
            ->where('node', $targetNode)
            ->value('id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vmStatusForNodeMove(ProxmoxServer $server, string $node, int $vmid, VirtualMachine $vm): ?array
    {
        try {
            return $this->proxmox->vmStatus($server, $node, $vmid);
        } catch (Throwable $exception) {
            Log::warning('Unable to verify VM status during admin node move', [
                'virtual_machine_id' => $vm->id,
                'proxmox_server_id' => $server->id,
                'node' => $node,
                'vmid' => $vmid,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
