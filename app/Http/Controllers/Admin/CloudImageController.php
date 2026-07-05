<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudImage;
use App\Models\CloudImageNodeMapping;
use App\Models\ProxmoxServer;
use App\Models\VmBundle;
use App\Services\ProxmoxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CloudImageController extends Controller
{
    public function __construct(private readonly ProxmoxService $proxmox) {}

    public function index(): View
    {
        return view('admin.cloud-images.index', [
            'images' => CloudImage::query()->with(['proxmoxServer', 'allowedBundles', 'nodeMappings'])->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.cloud-images.create', $this->formData(new CloudImage([
            'os_family' => 'ubuntu',
            'logo_key' => 'ubuntu',
            'default_username' => 'ubuntu',
            'disk_device' => 'scsi0',
            'network_bridge' => 'vmbr1',
            'ostype' => 'l26',
            'cloud_init_enabled' => true,
            'min_cpu_cores' => 1,
            'min_ram_gb' => 1,
            'min_disk_gb' => 10,
            'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $bundleIds = $data['bundle_ids'] ?? [];
        unset($data['bundle_ids']);

        $nodeMappings = $data['node_mappings'] ?? [];
        unset($data['node_mappings']);

        $image = DB::transaction(function () use ($data, $bundleIds, $nodeMappings): CloudImage {
            $image = CloudImage::create($data);
            $image->allowedBundles()->sync($bundleIds);
            $this->syncNodeMappings($image, $nodeMappings);

            return $image;
        });

        return redirect()->route('admin.cloud-images.index')->with('status', 'Cloud image saved.');
    }

    public function edit(CloudImage $cloudImage): View
    {
        return view('admin.cloud-images.edit', $this->formData($cloudImage));
    }

    public function update(Request $request, CloudImage $cloudImage): RedirectResponse
    {
        $data = $this->validated($request, $cloudImage);
        $bundleIds = $data['bundle_ids'] ?? [];
        unset($data['bundle_ids']);

        $nodeMappings = $data['node_mappings'] ?? [];
        unset($data['node_mappings']);

        DB::transaction(function () use ($cloudImage, $data, $bundleIds, $nodeMappings): void {
            $cloudImage->update($data);
            $cloudImage->allowedBundles()->sync($bundleIds);
            $this->syncNodeMappings($cloudImage, $nodeMappings);
        });

        return redirect()->route('admin.cloud-images.index')->with('status', 'Cloud image updated.');
    }

    public function destroy(CloudImage $cloudImage): RedirectResponse
    {
        $cloudImage->delete();

        return redirect()->route('admin.cloud-images.index')->with('status', 'Cloud image deleted.');
    }

    private function formData(CloudImage $image): array
    {
        return [
            'image' => $image,
            'bundles' => VmBundle::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('monthly_price')
                ->orderBy('name')
                ->get(),
            'selectedBundleIds' => $image->exists
                ? $image->allowedBundles()->pluck('vm_bundles.id')->all()
                : [],
            'servers' => ProxmoxServer::query()->orderBy('datacenter')->orderBy('name')->pluck('name', 'id'),
            'serverOptionUrls' => ProxmoxServer::query()->get()->mapWithKeys(fn (ProxmoxServer $server): array => [
                (string) $server->id => route('admin.virtual-machines.options', $server),
            ]),
            'existingNodeMappings' => $image->exists
                ? $image->nodeMappings()->orderBy('node')->get()->map(fn (CloudImageNodeMapping $mapping): array => [
                    'node' => $mapping->node,
                    'template_vmid' => (string) $mapping->template_vmid,
                    'storage' => $mapping->storage,
                    'network_bridge' => $mapping->network_bridge,
                    'template_version' => $mapping->template_version,
                    'is_enabled' => $mapping->is_enabled,
                ])->values()
                : collect(),
            'osFamilies' => [
                'ubuntu' => 'Ubuntu',
                'debian' => 'Debian',
                'rocky' => 'Rocky Linux',
                'router_os' => 'RouterOS',
                'windows' => 'Windows Server',
            ],
            'logoKeys' => [
                'ubuntu' => 'Ubuntu',
                'debian' => 'Debian',
                'rocky' => 'Rocky Linux',
                'router_os' => 'RouterOS',
                'windows' => 'Windows Server',
            ],
        ];
    }

    private function validated(Request $request, ?CloudImage $image = null): array
    {

        $data = $request->validate([
            'proxmox_server_id' => ['required', 'integer', 'exists:proxmox_servers,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cloud_images', 'slug')->ignore($image)],
            'description' => ['nullable', 'string', 'max:1000'],
            'os_family' => ['required', Rule::in(['ubuntu', 'debian', 'rocky', 'router_os', 'windows'])],
            'os_version' => ['required', 'string', 'max:100'],
            'logo_key' => ['required', Rule::in(['ubuntu', 'debian', 'rocky', 'router_os', 'windows'])],
            'node' => ['nullable', 'string', 'max:255'],
            'template_vmid' => ['nullable', 'integer', 'min:1'],
            'default_username' => ['required', 'string', 'max:64'],
            'post_installation_script' => ['nullable', 'string', 'max:20000'],
            'storage' => ['nullable', 'string', 'max:255'],
            'disk_device' => ['required', 'string', 'max:32'],
            'network_bridge' => ['nullable', 'string', 'max:64'],
            'ostype' => ['required', 'string', 'max:32'],
            'cloud_init_enabled' => ['nullable', 'boolean'],
            'min_cpu_cores' => ['required', 'integer', 'min:1', 'max:512'],
            'min_ram_gb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'min_disk_gb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'bundle_ids' => ['nullable', 'array'],
            'bundle_ids.*' => ['integer', Rule::exists('vm_bundles', 'id')->where('is_active', true)],
            'node_mappings' => ['nullable', 'array'],
            'node_mappings.*.node' => ['required', 'string', 'max:255'],
            'node_mappings.*.template_vmid' => ['nullable', 'integer', 'min:1'],
            'node_mappings.*.storage' => ['nullable', 'string', 'max:255'],
            'node_mappings.*.network_bridge' => ['nullable', 'string', 'max:64'],
            'node_mappings.*.template_version' => ['nullable', 'string', 'max:255'],
            'node_mappings.*.is_enabled' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['logo_key'] = $data['logo_key'] ?: $data['os_family'];
        $data['post_installation_script'] = $data['os_family'] === 'router_os'
            ? (trim((string) ($data['post_installation_script'] ?? '')) ?: null)
            : null;
        $data['cloud_init_enabled'] = $request->has('cloud_init_enabled')
            ? $request->boolean('cloud_init_enabled')
            : ($image?->cloud_init_enabled ?? true);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['bundle_ids'] = array_values(array_map('intval', $data['bundle_ids'] ?? []));
        $data['provider'] = 'proxmox';
        $primaryMapping = collect($data['node_mappings'] ?? [])->first(
            fn (array $mapping): bool => filter_var($mapping['is_enabled'] ?? false, FILTER_VALIDATE_BOOL)
                && filled($mapping['template_vmid'] ?? null)
        );
        if ($primaryMapping) {
            $data['node'] = $primaryMapping['node'];
            $data['template_vmid'] = (int) $primaryMapping['template_vmid'];
            $data['storage'] = $primaryMapping['storage'] ?: null;
            $data['network_bridge'] = $primaryMapping['network_bridge'] ?: 'vmbr1';
        }

        if ($data['is_active'] && empty($data['bundle_ids'])) {
            throw ValidationException::withMessages([
                'bundle_ids' => 'برای Cloud Image فعال باید حداقل یک پلن انتخاب شود.',
            ]);
        }

        if ($data['is_active'] && ! collect($data['node_mappings'] ?? [])->contains(
            fn (array $mapping): bool => filter_var($mapping['is_enabled'] ?? false, FILTER_VALIDATE_BOOL)
                && filled($mapping['template_vmid'] ?? null)
        ) && ! (filled($data['node'] ?? null) && filled($data['template_vmid'] ?? null))
            && ! ($image?->exists && empty($data['node_mappings']))) {
            throw ValidationException::withMessages([
                'node_mappings' => 'برای Cloud Image فعال باید حداقل یک node mapping فعال انتخاب شود.',
            ]);
        }

        return $data;
    }

    /** @param array<int, array<string, mixed>> $mappings */
    private function syncNodeMappings(CloudImage $image, array $mappings): void
    {
        if ($mappings === []) {
            if ($image->nodeMappings()->doesntExist() && filled($image->node) && $image->template_vmid) {
                $image->nodeMappings()->create([
                    'proxmox_server_id' => $image->proxmox_server_id,
                    'node' => $image->node,
                    'template_vmid' => $image->template_vmid,
                    'storage' => $image->storage,
                    'network_bridge' => $image->network_bridge ?: 'vmbr1',
                    'is_enabled' => true,
                ]);
            }

            return;
        }

        $server = ProxmoxServer::query()->findOrFail($image->proxmox_server_id);
        $inventory = $this->proxmox->vmCreationOptions($server);
        $templates = collect($inventory['os_templates'] ?? []);
        $storages = collect($inventory['disk_storages'] ?? []);
        $bridges = collect($inventory['bridges'] ?? []);
        $keptNodes = [];

        foreach ($mappings as $mapping) {
            $enabled = filter_var($mapping['is_enabled'] ?? false, FILTER_VALIDATE_BOOL);
            $node = trim((string) $mapping['node']);
            $templateVmid = (int) ($mapping['template_vmid'] ?? 0);
            $storage = trim((string) ($mapping['storage'] ?? ''));
            $bridge = trim((string) ($mapping['network_bridge'] ?? ''));

            if ($enabled && ! $templates->contains(fn (array $item): bool => $item['node'] === $node && (int) $item['vmid'] === $templateVmid)) {
                throw ValidationException::withMessages(['node_mappings' => "Template {$templateVmid} روی node {$node} پیدا نشد."]);
            }
            if ($enabled && ! $storages->contains(fn (array $item): bool => $item['node'] === $node && $item['storage'] === $storage)) {
                throw ValidationException::withMessages(['node_mappings' => "Storage {$storage} روی node {$node} در دسترس نیست."]);
            }
            if ($enabled && ! $bridges->contains(fn (array $item): bool => $item['node'] === $node && $item['iface'] === $bridge)) {
                throw ValidationException::withMessages(['node_mappings' => "Bridge {$bridge} روی node {$node} در دسترس نیست."]);
            }

            $image->nodeMappings()->updateOrCreate(
                ['node' => $node],
                [
                    'proxmox_server_id' => $server->id,
                    'template_vmid' => $templateVmid,
                    'storage' => $storage,
                    'network_bridge' => $bridge ?: 'vmbr1',
                    'template_version' => trim((string) ($mapping['template_version'] ?? '')) ?: null,
                    'is_enabled' => $enabled,
                    'verified_at' => $enabled ? now() : null,
                    'verification_snapshot' => $enabled ? ['verified_from' => 'live_inventory'] : null,
                ],
            );
            $keptNodes[] = $node;
        }

        $image->nodeMappings()->whereNotIn('node', $keptNodes)->delete();

        $primary = $image->nodeMappings()->where('is_enabled', true)->orderBy('node')->first();
        if ($primary) {
            $image->forceFill([
                'node' => $primary->node,
                'template_vmid' => $primary->template_vmid,
                'storage' => $primary->storage,
                'network_bridge' => $primary->network_bridge,
            ])->save();
        }
    }
}
