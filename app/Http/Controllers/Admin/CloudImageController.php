<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudImage;
use App\Models\ProxmoxServer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CloudImageController extends Controller
{
    public function index(): View
    {
        return view('admin.cloud-images.index', [
            'images' => CloudImage::query()->with('proxmoxServer')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.cloud-images.create', $this->formData(new CloudImage([
            'os_family' => 'ubuntu',
            'logo_key' => 'ubuntu',
            'default_username' => 'ubuntu',
            'disk_device' => 'scsi0',
            'network_bridge' => 'vmbr0',
            'ostype' => 'l26',
            'min_cpu_cores' => 1,
            'min_ram_gb' => 1,
            'min_disk_gb' => 10,
            'is_active' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        CloudImage::create($this->validated($request));

        return redirect()->route('admin.cloud-images.index')->with('status', 'Cloud image saved.');
    }

    public function edit(CloudImage $cloudImage): View
    {
        return view('admin.cloud-images.edit', $this->formData($cloudImage));
    }

    public function update(Request $request, CloudImage $cloudImage): RedirectResponse
    {
        $cloudImage->update($this->validated($request, $cloudImage));

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
            'servers' => ProxmoxServer::query()->orderBy('datacenter')->orderBy('name')->pluck('name', 'id'),
            'osFamilies' => [
                'ubuntu' => 'Ubuntu',
                'debian' => 'Debian',
                'rocky' => 'Rocky Linux',
                'windows' => 'Windows Server',
            ],
            'logoKeys' => [
                'ubuntu' => 'Ubuntu',
                'debian' => 'Debian',
                'rocky' => 'Rocky Linux',
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
            'os_family' => ['required', Rule::in(['ubuntu', 'debian', 'rocky', 'windows'])],
            'os_version' => ['required', 'string', 'max:100'],
            'logo_key' => ['required', Rule::in(['ubuntu', 'debian', 'rocky', 'windows'])],
            'node' => ['required', 'string', 'max:255'],
            'template_vmid' => ['required', 'integer', 'min:1'],
            'default_username' => ['required', 'string', 'max:64'],
            'storage' => ['nullable', 'string', 'max:255'],
            'disk_device' => ['required', 'string', 'max:32'],
            'network_bridge' => ['required', 'string', 'max:64'],
            'ostype' => ['required', 'string', 'max:32'],
            'min_cpu_cores' => ['required', 'integer', 'min:1', 'max:512'],
            'min_ram_gb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'min_disk_gb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['logo_key'] = $data['logo_key'] ?: $data['os_family'];
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
