<?php

namespace App\Http\Resources\Api;

use App\Models\VirtualMachine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class VirtualMachineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var VirtualMachine $vm */
        $vm = $this->resource;
        $sshReady = filled($vm->ip_address) && $vm->provisioning_status === VirtualMachine::PROVISION_READY;
        $failure = $this->failure($vm);
        $data = [
            'uuid' => $vm->uuid,
            'display_name' => $vm->display_name,
            'name' => $vm->name,
            'hostname' => $vm->hostname,
            'project_uuid' => $vm->project?->uuid,
            'provider' => $vm->provider,
            'location' => $vm->infrastructureLocation ? [
                'id' => $vm->infrastructureLocation->id,
                'name' => $vm->infrastructureLocation->name,
                'region' => $vm->infrastructureLocation->region,
            ] : null,
            'image' => $vm->cloudImage ? [
                'id' => $vm->cloudImage->id,
                'name' => $vm->cloudImage->name,
                'os_family' => $vm->cloudImage->os_family,
                'os_version' => $vm->cloudImage->os_version,
            ] : null,
            'bundle' => $vm->bundle ? [
                'id' => $vm->bundle->id,
                'name' => $vm->bundle->name,
            ] : null,
            'resources' => [
                'cpu_cores' => (int) $vm->cpu_cores,
                'ram_gb' => (int) $vm->ram_gb,
                'disk_gb' => (int) $vm->disk_gb,
            ],
            'ip_address' => $vm->ip_address,
            'login_username' => $vm->login_username,
            'status' => $vm->status,
            'provisioning_status' => $vm->provisioning_status,
            'is_deleting' => $vm->isDeleting(),
            'is_deleted' => $vm->isDeleted(),
            'is_action_locked' => $vm->isActionLocked(),
            'ssh_ready' => $sshReady,
            'ssh_command' => $sshReady ? 'ssh '.($vm->login_username ?: 'root').'@'.$vm->ip_address : null,
            'vmid' => $vm->isProxmox() ? $vm->vmid : null,
            'node' => $vm->isProxmox() ? $vm->node : null,
            'failure' => $failure,
            'created_at' => $vm->created_at?->toIso8601String(),
            'updated_at' => $vm->updated_at?->toIso8601String(),
        ];

        if ($request->attributes->has('api_generated_password') && filled($request->attributes->get('api_generated_password'))) {
            $data['generated_login_password'] = $request->attributes->get('api_generated_password');
        }

        return $data;
    }

    private function failure(VirtualMachine $vm): ?array
    {
        if ($vm->provisioning_status === VirtualMachine::PROVISION_FAILED) {
            return ['type' => 'provisioning', 'message' => Str::limit((string) data_get($vm->remote_state, 'provisioning_error', 'Provisioning failed.'), 500)];
        }

        if (filled($vm->delete_error)) {
            return ['type' => 'deletion', 'message' => Str::limit((string) $vm->delete_error, 500)];
        }

        return null;
    }

    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('api_request_id')]];
    }
}
