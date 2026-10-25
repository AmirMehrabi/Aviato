<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DomainSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'access' => ['nullable', 'in:selected']]);
        $query = Customer::query();
        if ($search = trim($filters['q'] ?? '')) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%');
            });
        }
        if (($filters['access'] ?? '') === 'selected') {
            $query->where('domain_management_enabled', true);
        }

        return view('admin.settings.domains', [
            'customers' => $query->orderByDesc('domain_management_enabled')->orderBy('name')->paginate(20)->withQueryString(),
            'selectedCount' => Customer::where('domain_management_enabled', true)->count(),
            'enabled' => AppSetting::domainManagementEnabled(),
            'tokenConfigured' => filled(AppSetting::getValue(AppSetting::DOMAIN_RESELLER_TOKEN)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'domain_reseller_token' => ['nullable', 'string', 'max:10000'], 'sales_enabled' => ['sometimes', 'boolean'], 'default_nameservers' => ['nullable', 'string', 'max:1500']]);
        $token = preg_replace('/^Bearer\s+/i', '', trim($data['domain_reseller_token'] ?? ''));
        if ($token !== '' && preg_match('/\s/', $token)) {
            return back()->withErrors(['domain_reseller_token' => 'توکن معتبر وارد کنید.']);
        }
        if ($data['enabled'] && $token === '' && blank(AppSetting::getValue(AppSetting::DOMAIN_RESELLER_TOKEN))) {
            return back()->withErrors(['domain_reseller_token' => 'برای فعال‌سازی بخش دامنه، توکن سرویس را وارد کنید.']);
        }

        $nameservers = preg_split('/[\s,]+/', strtolower(trim($data['default_nameservers'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        if ($nameservers && (count($nameservers) < 2 || count($nameservers) > 6 || count(array_unique($nameservers)) !== count($nameservers) || collect($nameservers)->contains(fn ($ns) => ! preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/', $ns)))) {
            return back()->withErrors(['default_nameservers' => 'بین دو تا شش نیم‌سرور معتبر و متفاوت وارد کنید.']);
        }
        DB::transaction(function () use ($data, $token, $nameservers): void {
            if (array_key_exists('sales_enabled', $data)) {
                AppSetting::setValue('domains.sales_enabled', (bool) $data['sales_enabled'], 'boolean', 'domains');
            }
            if (array_key_exists('default_nameservers', $data)) {
                AppSetting::setValue('domains.default_nameservers', $nameservers, 'json', 'domains');
            }
            if ($token !== '') {
                AppSetting::setValue(AppSetting::DOMAIN_RESELLER_TOKEN, $token, 'string', 'domains');
            }
            AppSetting::setValue(AppSetting::DOMAIN_MANAGEMENT_ENABLED, (bool) $data['enabled'], 'boolean', 'domains');
        });

        return to_route('admin.settings.domains.edit')->with('status', 'تنظیمات دامنه ذخیره شد.');
    }

    public function updateAccess(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        // This flag is intentionally excluded from public/customer mass assignment.
        $customer->forceFill(['domain_management_enabled' => (bool) $data['enabled']])->save();

        if ($data['enabled'] && ! AppSetting::domainManagementEnabled()) {
            return back()->with('status', 'مشتری انتخاب شد. برای نمایش منوی دامنه، کلید «فعال‌سازی بخش دامنه برای مشتریان منتخب» را هم روشن کنید.');
        }

        return back()->with('status', $data['enabled'] ? 'دسترسی دامنه برای مشتری فعال شد.' : 'دسترسی دامنه برای مشتری لغو شد.');
    }
}
