<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VmBundle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VmBundleController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.bundles.index', [
            'bundles' => VmBundle::query()->orderBy('sort_order')->orderBy('monthly_price')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.billing.bundles.create', ['bundle' => new VmBundle(['ip_count' => 1, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        VmBundle::create($this->validated($request));

        return redirect()->route('admin.billing.bundles.index')->with('status', 'باندل ذخیره شد.');
    }

    public function edit(VmBundle $bundle): View
    {
        return view('admin.billing.bundles.edit', ['bundle' => $bundle]);
    }

    public function update(Request $request, VmBundle $bundle): RedirectResponse
    {
        $bundle->update($this->validated($request, $bundle));

        return redirect()->route('admin.billing.bundles.index')->with('status', 'باندل به‌روزرسانی شد.');
    }

    public function destroy(VmBundle $bundle): RedirectResponse
    {
        $bundle->delete();

        return redirect()->route('admin.billing.bundles.index')->with('status', 'باندل حذف شد.');
    }

    private function validated(Request $request, ?VmBundle $bundle = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('vm_bundles', 'slug')->ignore($bundle)],
            'description' => ['nullable', 'string', 'max:1000'],
            'cpu_cores' => ['required', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'disk_gb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'ip_count' => ['required', 'integer', 'min:0', 'max:128'],
            'monthly_price' => ['required', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
