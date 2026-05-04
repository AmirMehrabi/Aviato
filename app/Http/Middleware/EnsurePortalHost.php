<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalHost
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $domain = config("portals.$portal.domain");

        if ($domain && $request->getHost() !== $domain) {
            abort(404);
        }

        return $next($request);
    }
}
