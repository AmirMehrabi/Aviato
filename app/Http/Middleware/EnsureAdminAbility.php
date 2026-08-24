<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        abort_unless($request->user('admin') && AdminAccess::allows($request->user('admin'), $ability), 403, 'Access denied.');

        return $next($request);
    }
}
