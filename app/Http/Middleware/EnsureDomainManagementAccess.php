<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDomainManagementAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->query('from') === 'homepage'
            && ! $request->user('customer')?->canPurchaseDomains()) {
            return response()->view('customer.domains.access-needed', ['domain' => mb_substr((string) $request->query('domain', ''), 0, 253)], 403);
        }
        abort_unless($request->user('customer')?->canManageDomains(), 403, 'دسترسی به بخش دامنه برای حساب شما فعال نیست.');

        return $next($request);
    }
}
