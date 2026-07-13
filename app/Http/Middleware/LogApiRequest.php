<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('api_request_id', $requestId);
        $startedAt = microtime(true);
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->record($request, $requestId, $startedAt, null, $this->exceptionFailureType($exception));

            throw $exception;
        }

        $this->record($request, $requestId, $startedAt, $response);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function record(Request $request, string $requestId, float $startedAt, ?Response $response, ?string $failureType = null): void
    {
        $request->attributes->set('api_audit_recorded', true);

        $user = $request->user('sanctum');
        $token = $user?->currentAccessToken();
        $authorization = (string) $request->header('Authorization');

        ApiRequestLog::create([
            'request_id' => $requestId,
            'customer_id' => $user?->getKey(),
            'personal_access_token_id' => $token?->getKey(),
            'token_fingerprint' => $authorization !== '' ? substr(hash('sha256', $authorization), 0, 16) : null,
            'method' => $request->method(),
            'route' => $request->route()?->uri(),
            'status_code' => $response?->getStatusCode() ?? $this->exceptionStatus($failureType),
            'failure_type' => $failureType ?? (($response?->getStatusCode() ?? 500) >= 400 ? $this->failureType($response) : null),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'query' => $this->redactQuery($request->query()),
            'request_bytes' => (int) ($request->header('Content-Length') ?: 0),
            'response_bytes' => $response ? strlen((string) $response->getContent()) : 0,
        ]);
    }

    private function exceptionFailureType(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AuthenticationException => 'authentication',
            $exception instanceof ValidationException => 'validation',
            $exception instanceof ThrottleRequestsException => 'rate_limit',
            $exception instanceof ModelNotFoundException => 'not_found',
            $exception instanceof UrlGenerationException => 'not_found',
            default => 'server_error',
        };
    }

    private function exceptionStatus(?string $failureType): int
    {
        return match ($failureType) {
            'authentication' => 401,
            'validation' => 422,
            'rate_limit' => 429,
            'not_found' => 404,
            default => 500,
        };
    }

    private function failureType(Response $response): string
    {
        return match (true) {
            $response->getStatusCode() === 401 => 'authentication',
            $response->getStatusCode() === 403 => 'authorization',
            $response->getStatusCode() === 404 => 'not_found',
            $response->getStatusCode() === 422 => 'validation',
            $response->getStatusCode() === 429 => 'rate_limit',
            $response->getStatusCode() >= 500 => 'server_error',
            default => 'client_error',
        };
    }

    private function redactQuery(array $query): array
    {
        foreach (['token', 'api_key', 'key', 'password', 'secret'] as $key) {
            if (array_key_exists($key, $query)) {
                $query[$key] = '[REDACTED]';
            }
        }

        return $query;
    }
}
