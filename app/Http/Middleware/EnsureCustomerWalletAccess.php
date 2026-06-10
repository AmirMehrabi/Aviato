<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerWalletAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if (! $customer instanceof Customer || ! $customer->isSuspended()) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();
        $walletAllowed = str_starts_with($routeName, 'customer.wallet.')
            || str_starts_with($routeName, 'customer.wallet')
            || str_starts_with($routeName, 'customer.logout')
            || str_starts_with($routeName, 'customer.verification.')
            || str_starts_with($routeName, 'customer.password.')
            || str_starts_with($routeName, 'customer.invoices.')
            || str_starts_with($routeName, 'customer.payments.');

        if ($walletAllowed) {
            return $next($request);
        }

        return redirect()
            ->route('customer.suspension.notice')
            ->with('error', 'حساب شما تعلیق شده است و فقط امکان شارژ کیف پول را دارید.');
    }
}
