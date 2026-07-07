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
        $aliases = config("portals.$portal.aliases", []);
        $allowedHosts = array_filter(array_merge([$domain], is_array($aliases) ? $aliases : []));

        if ($allowedHosts !== [] && ! in_array($request->getHost(), $allowedHosts, true)) {
            abort(404);
        }

        return $next($request);
    }
}
