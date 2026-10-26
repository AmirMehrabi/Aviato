<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\DomainOperation;
use App\Models\DomainProduct;
use App\Models\ManagedDomain;
use App\Services\Domains\DomainManagementService;
use App\Services\Domains\DomainWorkspaceService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagedDomainController extends Controller
{
    public function __construct(private readonly DomainWorkspaceService $workspaces) {}

    public function show(Request $request, ManagedDomain $domain): View
    {
        $project = $this->workspaces->domain($request, $domain);

        return view('customer.domains.show', ['customer' => $request->user('customer'), 'activeProject' => $project, 'canManageDomain' => $this->workspaces->canManage($project, $request->user('customer')), 'canPayDomain' => (int) $domain->customer_id === (int) $request->user('customer')->id && $this->workspaces->canBill($project, $request->user('customer')), 'canViewDomainBilling' => $this->workspaces->canBill($project, $request->user('customer')), 'domain' => $domain, 'wallets' => app(WalletService::class), 'renewals' => DomainProduct::where('provider', $domain->provider)->where('extension', $domain->extension)->where('operation', 'renew')->where('enabled', true)->orderBy('period')->get(), 'orders' => $domain->orders()->latest()->get(), 'operations' => DomainOperation::where('managed_domain_id', $domain->id)->latest()->limit(10)->get()]);
    }

    public function nameservers(Request $request, ManagedDomain $domain, DomainManagementService $management): RedirectResponse
    {
        $this->workspaces->domain($request, $domain, manage: true);
        abort_unless($domain->status === 'active', 422);
        $data = $request->validate(['nameservers' => ['required', 'array', 'list', 'min:2', 'max:'.($domain->provider === 'irpower' ? 5 : 6)], 'nameservers.*' => ['required', 'string', 'max:253', 'distinct:ignore_case', 'regex:/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/i']]);
        $management->nameservers($domain, array_map('strtolower', $data['nameservers']));

        return back()->with('status', 'درخواست تغییر نیم‌سرورها ثبت شد. نتیجه پس از تأیید سرویس‌دهنده نمایش داده می‌شود؛ انتشار تغییر ممکن است زمان ببرد.');
    }

    public function autoRenew(Request $request, ManagedDomain $domain): RedirectResponse
    {
        $this->workspaces->domain($request, $domain, billing: true);
        abort_unless((int) $domain->customer_id === (int) $request->user('customer')->id, 403, 'تنظیم تمدید خودکار بر عهده پرداخت‌کننده دامنه است.');
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'limit_toman' => ['required_if:enabled,1', 'nullable', 'integer', 'min:1', 'max:50000000'], 'consent' => ['accepted_if:enabled,1']]);
        $domain->update(['auto_renew' => (bool) $data['enabled'], 'auto_renew_limit' => $data['enabled'] ? (int) $data['limit_toman'] * 10 : null, 'auto_renew_consented_at' => $data['enabled'] ? now() : null]);

        return back()->with('status', $data['enabled'] ? 'تمدید خودکار با سقف مبلغ انتخابی فعال شد.' : 'تمدید خودکار خاموش شد. دامنه تا تاریخ انقضا معتبر می‌ماند.');
    }
}
