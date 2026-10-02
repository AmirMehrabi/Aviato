<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeAdminRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');
        abort_unless($user && AdminAccess::allowsRoute($user, (string) $request->route()?->getName(), $request->method()), 403, 'Access denied.');

        return $next($request);
    }
}
