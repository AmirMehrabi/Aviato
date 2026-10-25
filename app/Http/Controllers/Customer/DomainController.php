<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckDomainRequest;
use App\Models\AppSetting;
use App\Models\DomainOrder;
use App\Models\ManagedDomain;
use App\Services\Domains\DomainAvailabilityService;
use App\Services\Domains\DomainPricingService;
use App\Services\Domains\UnsupportedDomainExtension;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DomainController extends Controller
{
    public function index(Request $request): View
    {
        return $this->page($request);
    }

    public function check(CheckDomainRequest $request, DomainAvailabilityService $domains): JsonResponse|View
    {
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

        if ($result['available'] && ! $result['premium'] && AppSetting::domainSalesEnabled()) {
            $result['retail'] = app(DomainPricingService::class)->customerPrice($result['domain']);
        }

        return $request->expectsJson()
            ? response()->json(['data' => $result])
            : $this->page($request, $result);
    }

    private function page(Request $request, ?array $result = null, string $lookupError = ''): View
    {
        return view('customer.domains.index', ['customer' => $request->user('customer'), 'wallets' => app(WalletService::class), 'result' => $result, 'lookupError' => $lookupError, 'ownedDomains' => ManagedDomain::where('customer_id', $request->user('customer')->id)->orderBy('expires_at')->get(), 'domainOrders' => DomainOrder::where('customer_id', $request->user('customer')->id)->latest()->limit(15)->get(), 'canSearch' => $request->user('customer')->canPurchaseDomains(), 'salesEnabled' => AppSetting::domainSalesEnabled()]);
    }
}
