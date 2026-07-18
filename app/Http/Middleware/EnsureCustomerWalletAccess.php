<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerWalletAccess
{
    public function __construct(
        private readonly ProjectAccessService $projects,
        private readonly WalletService $wallets,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');
        $activeProject = $customer instanceof Customer ? $this->projects->activeProject($request, $customer) : null;
        $billingOwner = $activeProject?->owner ?? $customer;

        if (! $billingOwner instanceof Customer || ! $this->wallets->isWalletDepleted($billingOwner)) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();
        $walletAllowed = str_starts_with($routeName, 'customer.wallet.')
            || str_starts_with($routeName, 'customer.wallet')
            || str_starts_with($routeName, 'customer.logout')
            || str_starts_with($routeName, 'customer.verification.')
            || str_starts_with($routeName, 'customer.password.')
            || str_starts_with($routeName, 'customer.invoices.')
            || str_starts_with($routeName, 'customer.payments.')
            || str_starts_with($routeName, 'customer.projects.');

        if ($walletAllowed) {
            return $next($request);
        }

        return redirect()
            ->route('customer.suspension.notice')
            ->with('error', 'موجودی کیف پول شما کافی نیست. فعلا فقط شارژ کیف پول و پرداخت ها در دسترس است.');
    }
}
