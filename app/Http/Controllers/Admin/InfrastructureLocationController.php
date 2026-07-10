<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\HetznerServerType;
use App\Models\InfrastructureLocation;
use App\Models\VmBundle;
use App\Models\VmBundleLocationMapping;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InfrastructureLocationController extends Controller
{
    public function index(): View
    {
        return view('admin.infrastructure-locations.index', [
            'locations' => InfrastructureLocation::query()
                ->with(['proxmoxServer', 'hetznerAccount', 'bundleMappings.bundle', 'bundleMappings.hetznerServerType'])
                ->orderBy('provider')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(InfrastructureLocation $location): View
    {
        return view('admin.infrastructure-locations.edit', [
            'location' => $location->load(['bundleMappings']),
            'bundles' => VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get(),
            'serverTypes' => HetznerServerType::query()
                ->when($location->hetzner_account_id, fn ($query) => $query->where('hetzner_account_id', $location->hetzner_account_id))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'mappings' => $location->bundleMappings()->get()->keyBy('vm_bundle_id'),
        ]);
    }

    public function update(Request $request, InfrastructureLocation $location): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', Rule::in(array_keys(InfrastructureLocation::COUNTRIES))],
            'is_active' => ['nullable', 'boolean'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'bundle_ids' => ['nullable', 'array'],
            'bundle_ids.*' => ['integer', 'exists:vm_bundles,id'],
            'server_type_ids' => ['nullable', 'array'],
            'server_type_ids.*' => ['nullable', 'integer', Rule::exists('hetzner_server_types', 'id')],
        ]);

        $location->update([
            'name' => $data['name'],
            'country' => $data['country'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $bundleIds = collect($data['bundle_ids'] ?? [])->map(fn ($id): int => (int) $id);
        $serverTypeIds = collect($data['server_type_ids'] ?? []);

        VmBundleLocationMapping::query()
            ->where('infrastructure_location_id', $location->id)
            ->whereNotIn('vm_bundle_id', $bundleIds->all())
            ->update(['is_active' => false]);

        foreach ($bundleIds as $bundleId) {
            $serverTypeId = $location->isHetzner() ? (int) ($serverTypeIds->get((string) $bundleId) ?: 0) : null;
            $serverType = $serverTypeId ? HetznerServerType::query()->find($serverTypeId) : null;
            $usd = $serverType?->monthlyUsdForLocation($location->remote_name);

            VmBundleLocationMapping::query()->updateOrCreate(
                [
                    'vm_bundle_id' => $bundleId,
                    'infrastructure_location_id' => $location->id,
                ],
                [
                    'hetzner_server_type_id' => $serverTypeId ?: null,
                    'is_active' => true,
                    'monthly_price_usd' => $usd,
                    'monthly_price_irr' => $usd ? AppSetting::convertHetznerUsdToIrr($usd) : null,
                    'usd_to_irr_rate' => $usd ? AppSetting::hetznerUsdToIrrRate() : null,
                    'price_synced_at' => $usd ? now() : null,
                ],
            );
        }

        return redirect()
            ->route('admin.infrastructure-locations.index')
            ->with('status', 'Location updated.');
    }
}
