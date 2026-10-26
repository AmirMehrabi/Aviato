<?php

namespace App\Services\Domains;

use App\Models\Customer;
use App\Models\DomainOrder;
use App\Models\ManagedDomain;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Services\ProjectAccessService;
use Illuminate\Http\Request;

class DomainWorkspaceService
{
    public function __construct(private readonly ProjectAccessService $projects) {}

    public function firstOwned(Customer $customer): Project
    {
        return $customer->ownedProjects()->oldest('created_at')->orderBy('id')->first() ?? $customer->ensureDefaultProject();
    }

    public function active(Request $request): Project
    {
        $project = $this->projects->activeProject($request, $request->user('customer'));
        if ($request->filled('workspace')) {
            abort_unless($request->input('workspace') === $project->uuid, 409, 'فضای کاری تغییر کرده است. صفحه را تازه کنید و دوباره ادامه دهید.');
        }

        return $project;
    }

    public function canManage(Project $project, Customer $customer): bool
    {
        return in_array($this->projects->role($project, $customer), [ProjectMember::ROLE_OWNER, ProjectMember::ROLE_ADMIN], true);
    }

    public function canPurchase(Project $project, Customer $customer): bool
    {
        return (int) $project->owner_customer_id === (int) $customer->id && $customer->canPurchaseDomains();
    }

    public function canBill(Project $project, Customer $customer): bool
    {
        return $this->projects->canViewBilling($project, $customer);
    }

    public function domain(Request $request, ManagedDomain $domain, bool $manage = false, bool $billing = false): Project
    {
        return $this->resolve($request, $domain, $manage, $billing);
    }

    public function order(Request $request, DomainOrder $order, bool $mutate = false, bool $invoice = false): Project
    {
        $project = $this->resolve($request, $order, false, $invoice);
        if ($mutate) {
            abort_unless((int) $order->customer_id === (int) $request->user('customer')->id, 403, 'تکمیل و پرداخت این سفارش بر عهده خریدار آن است.');
        }

        return $project;
    }

    private function resolve(Request $request, ManagedDomain|DomainOrder $resource, bool $manage, bool $billing): Project
    {
        $project = $resource->project;
        $customer = $request->user('customer');
        abort_unless($project && $this->projects->membership($project, $customer), 404);
        abort_if($manage && ! $this->canManage($project, $customer), 403, 'برای تغییر تنظیمات دامنه، از مالک یا مدیر فضای کاری کمک بگیرید.');
        abort_if($billing && ! $this->canBill($project, $customer), 403, 'مشاهده اطلاعات مالی به دسترسی مالی فضای کاری نیاز دارد.');
        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            // Bookmarks and notifications open the domain's own workspace.
            $this->projects->switch($request, $customer, $project);
        } elseif (! $request->isMethod('GET')) {
            abort_unless($this->active($request)->id === $project->id, 409, 'فضای کاری تغییر کرده است. صفحه دامنه را دوباره باز کنید.');
        }

        return $project;
    }
}
