<?php

namespace App\Services;

use App\Models\MeteringInventoryAssignment;
use App\Models\MeteringInventoryChange;
use App\Models\VirtualMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeteringInventoryService
{
    public function closeVm(VirtualMachine $vm): void
    {
        MeteringInventoryAssignment::query()->where('virtual_machine_id', $vm->id)->where('active', true)->each(function ($assignment): void {
            $assignment->forceFill(['active' => false, 'valid_until' => now()])->save();
            $this->publish($assignment, 'deactivate');
        });
    }

    public function syncVm(VirtualMachine $vm): void
    {
        DB::transaction(function () use ($vm): void {
            $active = MeteringInventoryAssignment::query()
                ->where('virtual_machine_id', $vm->id)
                ->where('active', true)
                ->lockForUpdate()
                ->first();
            $eligible = filled($vm->uuid) && filled($vm->ip_address) && ! $vm->isDeleted() && ! $vm->isDeleting();

            if ($active && (! $eligible || $active->ip_address !== $vm->ip_address)) {
                $active->forceFill(['active' => false, 'valid_until' => now()])->save();
                $this->publish($active, 'deactivate');
                $active = null;
            }

            if (! $eligible) {
                return;
            }

            $active ??= MeteringInventoryAssignment::query()->create([
                'assignment_id' => (string) Str::ulid(),
                'virtual_machine_id' => $vm->id,
                'vm_uuid' => $vm->uuid,
                'ip_address' => $vm->ip_address,
                'valid_from' => $vm->reservedIpAddress?->assigned_at ?? $vm->reservedIpAddress?->reserved_at ?? now(),
                'active' => true,
                'display_name' => $vm->display_name,
            ]);
            $active->forceFill([
                'vm_uuid' => $vm->uuid,
                'mac_address' => $vm->mac_address,
                'provider' => $vm->provider ?: VirtualMachine::PROVIDER_PROXMOX,
                'provider_server_id' => $vm->proxmox_server_id ?: $vm->infrastructure_location_id,
                'provider_vm_id' => $vm->vmid ?: $vm->remote_id,
                'node' => $vm->node,
                'display_name' => $vm->display_name,
            ])->save();

            $payload = $this->payload($active);
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            if (! hash_equals((string) $active->payload_hash, $hash)) {
                $active->forceFill(['payload_hash' => $hash])->save();
                $this->publish($active, 'upsert', $payload);
            }
        });
    }

    public function refreshAll(): int
    {
        $count = 0;
        VirtualMachine::query()->with('reservedIpAddress')->orderBy('id')->chunkById(200, function ($vms) use (&$count): void {
            foreach ($vms as $vm) {
                $this->syncVm($vm);
                $count++;
            }
        });

        MeteringInventoryAssignment::query()->where('active', true)->whereDoesntHave('virtualMachine')->each(function ($assignment): void {
            $assignment->forceFill(['active' => false, 'valid_until' => now()])->save();
            $this->publish($assignment, 'deactivate');
        });

        return $count;
    }

    public function payload(MeteringInventoryAssignment $assignment): array
    {
        return [
            'assignment_id' => $assignment->assignment_id,
            'vm_uuid' => $assignment->vm_uuid,
            'ip_address' => $assignment->ip_address,
            'mac_address' => $assignment->mac_address,
            'provider' => $assignment->provider,
            'provider_server_id' => $assignment->provider_server_id,
            'provider_vm_id' => $assignment->provider_vm_id,
            'node' => $assignment->node,
            'display_name' => $assignment->display_name,
            'valid_from' => $assignment->valid_from?->utc()->format('Y-m-d\TH:i:s\Z'),
            'valid_until' => $assignment->valid_until?->utc()->format('Y-m-d\TH:i:s\Z'),
            'active' => (bool) $assignment->active,
        ];
    }

    private function publish(MeteringInventoryAssignment $assignment, string $action, ?array $payload = null): void
    {
        MeteringInventoryChange::query()->create([
            'assignment_id' => $assignment->assignment_id,
            'action' => $action,
            'payload' => $payload ?? $this->payload($assignment),
        ]);
    }
}
