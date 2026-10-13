<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpdrIntegrationAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ipdr.integration_token');
        $provided = (string) $request->bearerToken();
        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'A valid integration token is required.']], 401);
        }

        return $next($request);
    }
}
