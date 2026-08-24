<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminAuditService
{
    private const SENSITIVE = [
        'password', 'password_confirmation', 'current_password', 'token', 'secret',
        'authorization', 'cookie', 'api_token', 'private_key', 'merchant_password',
    ];

    public function record(Request $request, string $event, string $result, ?int $statusCode = null, array $metadata = [], array $changes = []): AdminAuditLog
    {
        $actor = $request->user('admin');
        [$targetType, $targetId] = $this->target($request);

        return AdminAuditLog::create([
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'event' => $event,
            'method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'path' => $request->path(),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'result' => $result,
            'status_code' => $statusCode,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'request_id' => $request->attributes->get('admin_audit_request_id') ?: (string) Str::uuid(),
            'metadata' => $this->redact($metadata),
            'changes' => $this->redact($changes),
            'created_at' => now(),
        ]);
    }

    public function authentication(Request $request, string $event, string $result, ?User $user = null): AdminAuditLog
    {
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        return $this->record($request, $event, $result, $result === 'success' ? 200 : 422, [
            'login' => $this->maskLogin((string) $request->input('login')),
        ]);
    }

    public function modelChanges(Model $model, array $before): array
    {
        $after = $model->exists ? $model->fresh()?->getAttributes() ?? [] : [];
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        $changes = [];

        foreach ($keys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changes[$key] = ['before' => $old, 'after' => $new];
            }
        }

        return $this->redact($changes);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function target(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof Model) {
                return [$value->getMorphClass(), (string) $value->getKey()];
            }
        }

        return [null, null];
    }

    private function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (collect(self::SENSITIVE)->contains(fn (string $needle): bool => str_contains(strtolower((string) $key), $needle))) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function maskLogin(string $login): string
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            [$name, $domain] = explode('@', $login, 2);

            return mb_substr($name, 0, 2).'***@'.$domain;
        }

        return $login === '' ? '' : '***'.mb_substr($login, -4);
    }
}
