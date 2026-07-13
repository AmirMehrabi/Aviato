<?php

use App\Http\Middleware\EnsureCustomerVmAccess;
use App\Http\Middleware\EnsureCustomerWalletAccess;
use App\Http\Middleware\EnsurePortalHost;
use App\Http\Middleware\EnsureResellerActive;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\LogApiRequest;
use App\Models\ApiRequestLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

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
            'customer.vm.access' => EnsureCustomerVmAccess::class,
            'reseller.active' => EnsureResellerActive::class,
            'role' => EnsureUserRole::class,
            'api.audit' => LogApiRequest::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
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
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*'));
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $requestId = $request->attributes->get('api_request_id') ?: (string) Str::uuid();

            if (! $request->attributes->get('api_audit_recorded')) {
                ApiRequestLog::create([
                    'request_id' => $requestId,
                    'method' => $request->method(),
                    'route' => $request->route()?->uri(),
                    'status_code' => 401,
                    'failure_type' => 'authentication',
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                    'query' => $request->query(),
                ]);
            }

            return response()->json([
                'error' => ['code' => 'unauthenticated', 'message' => 'A valid bearer token is required.'],
                'meta' => ['request_id' => $requestId],
            ], 401);
        });
    })->create();
