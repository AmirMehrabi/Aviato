<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResellerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if (! $customer || ! $customer->isReseller()) {
            abort(403, 'Access denied. Reseller status required.');
        }

        return $next($request);
    }
}
