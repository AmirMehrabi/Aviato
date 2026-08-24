<?php

namespace App\Http\Middleware;

use App\Services\AdminAuditService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditAdminMutation
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $request->attributes->set('admin_audit_request_id', (string) Str::uuid());
        $target = collect($request->route()?->parameters() ?? [])->first(fn ($value): bool => $value instanceof Model);
        $before = $target instanceof Model ? $target->getAttributes() : [];

        try {
            $response = $next($request);
            $this->audit->record(
                $request,
                (string) ($request->route()?->getName() ?: $request->path()),
                $response->getStatusCode() < 400 ? 'success' : ($response->getStatusCode() === 403 ? 'denied' : 'failed'),
                $response->getStatusCode(),
                ['input' => $request->except(['_token', '_method'])],
                $target instanceof Model ? $this->audit->modelChanges($target, $before) : [],
            );

            return $response;
        } catch (Throwable $exception) {
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $this->audit->record($request, (string) ($request->route()?->getName() ?: $request->path()), $status === 403 ? 'denied' : 'failed', $status);
            throw $exception;
        }
    }
}
