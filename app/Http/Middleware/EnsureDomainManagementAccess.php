<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDomainManagementAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('customer')?->canManageDomains(), 403, 'دسترسی به بخش دامنه برای حساب شما فعال نیست.');

        return $next($request);
    }
}
