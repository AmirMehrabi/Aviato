<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckDomainRequest;
use App\Models\AppSetting;
use App\Services\Domains\DomainAvailabilityService;
use App\Services\Domains\DomainPricingService;
use App\Services\Domains\DomainWorkspaceService;
use App\Services\Domains\UnsupportedDomainExtension;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DomainController extends Controller
{
    public function __construct(private readonly DomainWorkspaceService $workspaces) {}

    public function index(Request $request): View
    {
        return $this->page($request);
    }

    public function check(CheckDomainRequest $request, DomainAvailabilityService $domains): JsonResponse|View
    {
        $this->workspaces->active($request);
        try {
            $result = $domains->check($request->validated('domain'));
        } catch (UnsupportedDomainExtension) {
            throw ValidationException::withMessages(['domain' => 'این پسوند در حال حاضر پشتیبانی نمی‌شود. پسوند دیگری مثل ‎.com یا ‎.net را امتحان کنید.']);
        } catch (RuntimeException) {
            $message = 'در حال حاضر بررسی دامنه امکان‌پذیر نیست. کمی بعد دوباره تلاش کنید.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return $this->page($request, null, $message);
        }

        if ($result['available'] && $result['premium'] === false && AppSetting::domainSalesEnabled()) {
            $result['retail'] = app(DomainPricingService::class)->customerPrice($result['domain']);
        }

        return $request->expectsJson()
            ? response()->json(['data' => $result])
            : $this->page($request, $result);
    }

    private function page(Request $request, ?array $result = null, string $lookupError = ''): View
    {
        $customer = $request->user('customer');
        $project = $this->workspaces->active($request);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:253'], 'filter' => ['nullable', 'in:all,expiring,attention']], ['q.string' => 'نام دامنه را به صورت متن وارد کنید.', 'q.max' => 'نام دامنه بیش از حد طولانی است.', 'filter.in' => 'یکی از فیلترهای نمایش‌داده‌شده را انتخاب کنید.']);
        $allDomains = $project->domains()->get();
        $ownedDomains = $allDomains->filter(fn ($domain) => empty($filters['q']) || str_contains($domain->name, strtolower(trim($filters['q']))))
            ->filter(fn ($domain) => match ($filters['filter'] ?? 'all') {
                'expiring' => $domain->expires_at?->isFuture() && $domain->expires_at->lte(now()->addDays(30)),
                'attention' => $domain->needsAttention(),
                default => true,
            })->sortBy(fn ($domain) => ($domain->needsAttention() ? '0' : '1').($domain->expires_at?->format('Ymd') ?? '99999999').$domain->name);
        $orders = $project->domainOrders()->with('customer')->latest();
        $pendingOrders = (clone $orders)->whereIn('status', ['draft', 'awaiting_payment', 'funded', 'processing', 'confirming', 'needs_review'])->get();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $ownedDomains = new LengthAwarePaginator($ownedDomains->values()->forPage($page, 15)->values(), $ownedDomains->count(), 15, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $canSearch = $this->workspaces->canPurchase($project, $customer);

        return view('customer.domains.index', [
            'customer' => $customer, 'activeProject' => $project, 'wallets' => app(WalletService::class),
            'result' => $result, 'lookupError' => $lookupError, 'ownedDomains' => $ownedDomains,
            'domainCount' => $allDomains->count(), 'attentionCount' => $allDomains->filter->needsAttention()->count(),
            'expiringCount' => $allDomains->filter(fn ($domain) => $domain->expires_at?->isFuture() && $domain->expires_at->lte(now()->addDays(30)))->count(),
            'domainOrders' => $orders->paginate(15, ['*'], 'orders_page')->withQueryString(),
            'pendingOrders' => $pendingOrders,
            'canSearch' => $canSearch, 'salesEnabled' => AppSetting::domainSalesEnabled(),
            'showSearch' => $canSearch && ($result !== null || $lookupError !== '' || $request->filled('domain') || $request->boolean('register') || ($request->input('view') !== 'orders' && $allDomains->isEmpty() && $pendingOrders->isEmpty())),
        ]);
    }
}
