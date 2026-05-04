<?php

use App\Http\Middleware\EnsurePortalHost;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'portal.host' => EnsurePortalHost::class,
            'role' => EnsureUserRole::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            $adminDomain = config('portals.admin.domain');
            $portal = $adminDomain && $request->getHost() === $adminDomain ? 'admin' : 'customer';
            $domain = config("portals.$portal.domain");
            $path = '/'.config("portals.$portal.login_path");

            return $domain ? $request->getScheme().'://'.$domain.$path : $path;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
