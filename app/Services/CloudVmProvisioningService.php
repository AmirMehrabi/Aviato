<?php

namespace App\Services;

use App\Jobs\ProvisionCloudVirtualMachine;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CloudVmProvisioningService
{
    public function __construct(
        private readonly IpPoolService $ipPools,
        private readonly ProxmoxService $proxmox,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{vm: VirtualMachine, password: ?string}
     */
    public function create(Customer $customer, array $data, bool $dispatch = true): array
    {
        $image = CloudImage::query()
            ->where('is_active', true)
            ->with('proxmoxServer')
            ->findOrFail($data['cloud_image_id']);

        $server = $image->proxmoxServer;
        if (! $server || ! $server->is_active || $server->maintenance_mode) {
            throw new RuntimeException('The selected image is not available for provisioning.');
        }

        $resources = $this->resources($data);
        $this->assertMinimums($image, $resources);

        $password = $this->resolvePassword($data);
        $username = trim((string) ($data['login_username'] ?? '')) ?: $image->default_username;

        $vm = DB::transaction(function () use ($customer, $data, $image, $server, $resources, $password, $username): VirtualMachine {
            $name = trim((string) ($data['name'] ?? '')) ?: 'customer-vps-'.Str::lower(Str::random(6));
            $vm = VirtualMachine::create([
                'customer_id' => $customer->id,
                'proxmox_server_id' => $server->id,
                'vm_bundle_id' => $data['vm_bundle_id'] ?? null,
                'cloud_image_id' => $image->id,
                'template_vmid' => $image->template_vmid,
                'name' => $name,
                'hostname' => trim((string) ($data['hostname'] ?? '')) ?: $name,
                'node' => $image->node,
                'storage' => $image->storage,
                'os_template' => $image->name,
                'network_bridge' => $image->network_bridge,
                'login_username' => $username,
                'login_password' => $password,
                'ssh_public_key' => trim((string) ($data['ssh_public_key'] ?? '')) ?: null,
                'cpu_cores' => $resources['cpu_cores'],
                'ram_gb' => $resources['ram_gb'],
                'disk_gb' => $resources['disk_gb'],
                'ip_count' => 1,
                'status' => VirtualMachine::STATUS_STOPPED,
                'provisioning_status' => VirtualMachine::PROVISION_PENDING,
                'last_billed_at' => now(),
            ]);

            $vm->desired_state = $vm->desiredStateSnapshot() + [
                'start_after_create' => (bool) ($data['start_after_create'] ?? true),
                'onboot' => (bool) ($data['onboot'] ?? false),
                'disk_device' => $image->disk_device,
            ];
            $vm->save();

            return $vm;
        });

        try {
            $remoteAddresses = $this->proxmox->assignedGuestIpAddresses($server, $image->node);
            $this->ipPools->reserveForVm($vm, $remoteAddresses);
        } catch (Throwable $exception) {
            $vm->delete();

            throw $exception;
        }

        if ($dispatch) {
            ProvisionCloudVirtualMachine::dispatch($vm->id, [
                'start_after_create' => (bool) ($data['start_after_create'] ?? true),
                'onboot' => (bool) ($data['onboot'] ?? false),
            ]);
        }

        return ['vm' => $vm->refresh(), 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{cpu_cores: int, ram_gb: int, disk_gb: int}
     */
    private function resources(array $data): array
    {
        if (! empty($data['vm_bundle_id'])) {
            $bundle = VmBundle::findOrFail($data['vm_bundle_id']);

            return [
                'cpu_cores' => $bundle->cpu_cores,
                'ram_gb' => $bundle->ram_gb,
                'disk_gb' => $bundle->disk_gb,
            ];
        }

        return [
            'cpu_cores' => (int) ($data['cpu_cores'] ?? 1),
            'ram_gb' => (int) ($data['ram_gb'] ?? 1),
            'disk_gb' => (int) ($data['disk_gb'] ?? 10),
        ];
    }

    /**
     * @param  array{cpu_cores: int, ram_gb: int, disk_gb: int}  $resources
     */
    private function assertMinimums(CloudImage $image, array $resources): void
    {
        if ($resources['cpu_cores'] < $image->min_cpu_cores || $resources['ram_gb'] < $image->min_ram_gb || $resources['disk_gb'] < $image->min_disk_gb) {
            throw new RuntimeException("The selected resources are below {$image->name} minimum requirements.");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePassword(array $data): ?string
    {
        $password = trim((string) Arr::get($data, 'login_password', ''));

        if ($password !== '') {
            return $password;
        }

        if (filled((string) Arr::get($data, 'ssh_public_key', ''))) {
            return null;
        }

        return Str::password(18);
    }
}
