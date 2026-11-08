<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\DomainSearchRequest;
use App\Models\AppSetting;
use App\Services\Domains\DomainAvailabilityService;
use App\Services\Domains\DomainPricingService;
use App\Services\Domains\UnsupportedDomainExtension;
use Illuminate\Validation\ValidationException;

class DomainSearchController extends Controller
{
    public function __invoke(DomainSearchRequest $request, DomainAvailabilityService $availability, DomainPricingService $pricing)
    {
        if (! AppSetting::domainManagementEnabled()) {
            return response()->json(['message' => 'جستجوی دامنه فعلاً فعال نیست. لطفاً با پشتیبانی تماس بگیرید.'], 503);
        }
        try {
            $result = $availability->check($request->validated('domain'));
        } catch (UnsupportedDomainExtension) {
            throw ValidationException::withMessages(['domain' => 'این پسوند در حال حاضر پشتیبانی نمی‌شود. پسوند دیگری را امتحان کنید.']);
        }
        $next = null;
        if ($result['available'] && $result['premium'] === false && AppSetting::domainSalesEnabled()
            && ($retail = $pricing->customerPrice($result['domain']))) {
            $result['retail'] = $retail;
            $next = route('customer.domains.index', ['register' => 1, 'domain' => $result['domain'], 'from' => 'homepage']);
        }

        // Never disclose reseller prices, credentials or raw provider messages.
        return response()->json(['data' => $result, 'continue_url' => $next]);
    }
}
