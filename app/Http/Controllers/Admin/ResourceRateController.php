<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourceRate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResourceRateController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.rates.index', [
            'rates' => ResourceRate::query()->orderBy('resource')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.billing.rates.create', ['rate' => new ResourceRate(['billing_policy' => ResourceRate::POLICY_RUNNING, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        ResourceRate::create($this->validated($request));

        return redirect()->route('admin.billing.rates.index')->with('status', 'قیمت منبع ذخیره شد.');
    }

    public function edit(ResourceRate $rate): View
    {
        return view('admin.billing.rates.edit', ['rate' => $rate]);
    }

    public function update(Request $request, ResourceRate $rate): RedirectResponse
    {
        $rate->update($this->validated($request, $rate));

        return redirect()->route('admin.billing.rates.index')->with('status', 'قیمت منبع به‌روزرسانی شد.');
    }

    public function destroy(ResourceRate $rate): RedirectResponse
    {
        $rate->delete();

        return redirect()->route('admin.billing.rates.index')->with('status', 'قیمت منبع حذف شد.');
    }

    private function validated(Request $request, ?ResourceRate $rate = null): array
    {
        $data = $request->validate([
            'resource' => ['required', 'string', 'max:80', Rule::unique('resource_rates', 'resource')->ignore($rate)],
            'label' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'monthly_price' => ['required', 'integer', 'min:0'],
            'billing_policy' => ['required', Rule::in([ResourceRate::POLICY_RUNNING, ResourceRate::POLICY_ALWAYS])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['hourly_price'] = $data['monthly_price'] / ResourceRate::hoursPerMonth();
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
