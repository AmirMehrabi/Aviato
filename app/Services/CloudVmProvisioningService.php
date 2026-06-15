<?php

namespace App\Services;

use App\Jobs\ProvisionCloudVirtualMachine;
use App\Models\CloudImage;
use App\Models\Customer;
use App\Models\Project;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CloudVmProvisioningService
{
    private const OS_PREFIXES = [
        'ubuntu' => 'UBNT',
        'debian' => 'DBN',
        'rocky' => 'RKL',
        'router_os' => 'ROS',
        'windows' => 'WND',
    ];

    public function __construct(
        private readonly IpPoolService $ipPools,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{vm: VirtualMachine, password: ?string}
     */
    public function create(Customer $customer, array $data, bool $dispatch = true, ?Project $project = null): array
    {
        $project ??= $customer->ensureDefaultProject();
        $project->loadMissing('owner');

        $image = CloudImage::query()
            ->where('is_active', true)
            ->with(['proxmoxServer', 'allowedBundles'])
            ->findOrFail($data['cloud_image_id']);

        $server = $image->proxmoxServer;
        if (! $server || ! $server->is_active || $server->maintenance_mode) {
            throw new RuntimeException('The selected image is not available for provisioning.');
        }

        $resources = $this->resources($data);
        $this->assertMinimums($image, $resources);
        $bundle = ! empty($data['vm_bundle_id'])
            ? VmBundle::query()->find((int) $data['vm_bundle_id'])
            : null;

        $cloudInitEnabled = (bool) $image->cloud_init_enabled;
        $password = $cloudInitEnabled ? $this->resolvePassword($data) : null;
        $username = $cloudInitEnabled ? (trim((string) ($data['login_username'] ?? '')) ?: $image->default_username) : null;
        $sshPublicKey = $cloudInitEnabled ? $this->normalizeSshPublicKeys((string) ($data['ssh_public_key'] ?? '')) : null;
        $node = trim((string) ($data['node'] ?? '')) ?: $image->node;
        $storage = trim((string) ($data['storage'] ?? '')) ?: $image->storage;
        $osTemplate = trim((string) ($data['os_template'] ?? '')) ?: $image->name;
        $networkBridge = trim((string) ($data['network_bridge'] ?? '')) ?: $image->network_bridge;

        $vm = DB::transaction(function () use ($customer, $project, $data, $image, $server, $bundle, $resources, $password, $username, $sshPublicKey, $node, $storage, $osTemplate, $networkBridge): VirtualMachine {
            $osPrefix = self::OS_PREFIXES[$image->os_family] ?? 'VM';
            $name = $this->generateUniqueVmName($bundle, $resources, $osPrefix);
            $vm = VirtualMachine::create([
                'customer_id' => $project->owner_customer_id,
                'project_id' => $project->id,
                'created_by_customer_id' => $customer->id,
                'proxmox_server_id' => $server->id,
                'vm_bundle_id' => $data['vm_bundle_id'] ?? null,
                'cloud_image_id' => $image->id,
                'template_vmid' => $image->template_vmid,
                'name' => $name,
                'display_name' => $data['display_name'] ?? null,
                'hostname' => $image->cloud_init_enabled ? Str::lower($name) : null,
                'node' => $node,
                'storage' => $storage,
                'os_template' => $osTemplate,
                'network_bridge' => $networkBridge,
                'login_username' => $username,
                'login_password' => $password,
                'ssh_public_key' => $sshPublicKey,
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

        $this->reserveRequiredIp($vm);
        $vm->forceFill(['network_bridge' => $networkBridge])->save();

        if ($dispatch) {
            ProvisionCloudVirtualMachine::dispatch($vm->id, [
                'start_after_create' => (bool) ($data['start_after_create'] ?? true),
                'onboot' => (bool) ($data['onboot'] ?? false),
            ])->onQueue(ProvisionCloudVirtualMachine::QUEUE);
        }

        return ['vm' => $vm->refresh(), 'password' => $password];
    }

    private function reserveRequiredIp(VirtualMachine $vm): void
    {
        try {
            $this->ipPools->reserveForVm($vm, []);
        } catch (Throwable $exception) {
            $vm->delete();

            throw $exception;
        }
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

        return Str::password(18);
    }

    /**
     * @param  array{cpu_cores: int, ram_gb: int, disk_gb: int}  $resources
     */
    private function generateUniqueVmName(?VmBundle $bundle, array $resources, string $osPrefix): string
    {
        $base = $osPrefix.'-'.now()->format('ym').'-'.$this->bundleSpecsToken($bundle, $resources);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $base.'-'.$this->uniqueKey();

            if (! VirtualMachine::query()->where('name', $candidate)->exists()) {
                return $candidate;
            }
        }

        do {
            $candidate = $base.'-'.$this->uniqueKey(10);
        } while (VirtualMachine::query()->where('name', $candidate)->exists());

        return $candidate;
    }

    /**
     * @param  array{cpu_cores: int, ram_gb: int, disk_gb: int}  $resources
     */
    private function bundleSpecsToken(?VmBundle $bundle, array $resources): string
    {
        $cpu = (int) ($bundle?->cpu_cores ?? $resources['cpu_cores']);
        $ram = (int) ($bundle?->ram_gb ?? $resources['ram_gb']);
        $disk = (int) ($bundle?->disk_gb ?? $resources['disk_gb']);

        return "{$cpu}C{$ram}G{$disk}G";
    }

    private function uniqueKey(int $length = 6): string
    {
        return Str::upper(Str::random($length));
    }

    private function normalizeSshPublicKeys(string $value): ?string
    {
        $keys = collect(preg_split('/\R/', str_replace("\r\n", "\n", trim($value))) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();

        return $keys === [] ? null : implode("\n", $keys);
    }
}
