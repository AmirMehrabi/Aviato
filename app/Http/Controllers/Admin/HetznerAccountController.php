<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HetznerAccount;
use App\Services\HetznerCatalogSyncService;
use App\Services\HetznerCloudService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class HetznerAccountController extends Controller
{
    public function __construct(
        private readonly HetznerCloudService $hetzner,
        private readonly HetznerCatalogSyncService $catalog,
    ) {}

    public function index(): View
    {
        return view('admin.hetzner-accounts.index', [
            'accounts' => HetznerAccount::query()
                ->withCount(['locations', 'images', 'serverTypes'])
                ->latest()
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.hetzner-accounts.create', [
            'account' => new HetznerAccount(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $account = HetznerAccount::create($data);

        return redirect()
            ->route('admin.hetzner-accounts.show', $account)
            ->with('status', 'Hetzner account saved. Run sync to import locations, images, and server types.');
    }

    public function show(HetznerAccount $hetznerAccount): View
    {
        return view('admin.hetzner-accounts.show', [
            'account' => $hetznerAccount->load(['locations.bundleMappings.bundle', 'hetznerLocations', 'images.cloudImage', 'serverTypes']),
        ]);
    }

    public function edit(HetznerAccount $hetznerAccount): View
    {
        return view('admin.hetzner-accounts.edit', ['account' => $hetznerAccount]);
    }

    public function update(Request $request, HetznerAccount $hetznerAccount): RedirectResponse
    {
        $data = $this->validated($request, $hetznerAccount);

        if (($data['api_token'] ?? '') === '') {
            unset($data['api_token']);
        }

        $hetznerAccount->update($data);

        return redirect()
            ->route('admin.hetzner-accounts.show', $hetznerAccount)
            ->with('status', 'Hetzner account updated.');
    }

    public function destroy(HetznerAccount $hetznerAccount): RedirectResponse
    {
        $hetznerAccount->delete();

        return redirect()->route('admin.hetzner-accounts.index')->with('status', 'Hetzner account deleted.');
    }

    public function test(HetznerAccount $hetznerAccount): RedirectResponse
    {
        try {
            $this->hetzner->test($hetznerAccount);
            $hetznerAccount->forceFill([
                'connection_status' => HetznerAccount::CONNECTION_ONLINE,
                'last_seen_at' => now(),
                'sync_error' => null,
            ])->save();

            return back()->with('status', 'Hetzner API connection succeeded.');
        } catch (Throwable $exception) {
            $hetznerAccount->forceFill([
                'connection_status' => HetznerAccount::CONNECTION_OFFLINE,
                'sync_error' => $exception->getMessage(),
            ])->save();

            return back()->with('error', 'Hetzner API connection failed: '.$exception->getMessage());
        }
    }

    public function sync(HetznerAccount $hetznerAccount): RedirectResponse
    {
        try {
            $this->catalog->sync($hetznerAccount);

            return back()->with('status', 'Hetzner catalog synced.');
        } catch (Throwable $exception) {
            return back()->with('error', 'Hetzner catalog sync failed: '.$exception->getMessage());
        }
    }

    private function validated(Request $request, ?HetznerAccount $account = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'api_token' => [$account ? 'nullable' : 'required', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
            'maintenance_mode' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'maintenance_mode' => $request->boolean('maintenance_mode'),
        ];
    }
}
