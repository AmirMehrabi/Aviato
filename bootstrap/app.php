<?php

use App\Http\Middleware\EnsureCustomerWalletAccess;
use App\Http\Middleware\EnsurePortalHost;
use App\Http\Middleware\EnsureResellerActive;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'portal.host' => EnsurePortalHost::class,
            'customer.wallet.access' => EnsureCustomerWalletAccess::class,
            'reseller.active' => EnsureResellerActive::class,
            'role' => EnsureUserRole::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'wallet/payments/*/callback',
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            $adminDomain = config('portals.admin.domain');
            $portal = $adminDomain && $request->getHost() === $adminDomain ? 'admin' : 'customer';

            return '/'.trim(config("portals.$portal.login_path"), '/');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            $adminDomain = config('portals.admin.domain');
            $portal = $adminDomain && $request->getHost() === $adminDomain ? 'admin' : 'customer';

            return '/'.trim(config("portals.$portal.home_path"), '/');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
