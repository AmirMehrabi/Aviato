<?php

namespace App\Services\Domains;

use App\Models\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DomainProvider
{
    public function request(string $method, string $path, array $data = []): array
    {
        $token = AppSetting::domainResellerToken();
        if ($token === '') {
            throw new RuntimeException('Domain provider not configured.');
        }
        // Never retry mutations: the provider does not document idempotency keys.
        try {
            $response = Http::baseUrl(DomainAvailabilityService::BASE_URL)->withToken($token)
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(20)->withoutRedirecting()
                ->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException) {
            throw new RuntimeException('Domain provider connection could not be confirmed.');
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('Domain provider response could not be confirmed.');
        }

        return $response->json();
    }

    public function check(string $domain, int $period, string $type): array
    {
        $data = $this->request('POST', '/domain/'.$domain.'/check', compact('period', 'type'));
        if (($data['domain'] ?? null) !== $domain || ! is_bool($data['available'] ?? null)) {
            throw new RuntimeException('Invalid availability result.');
        }

        return $data;
    }

    public function inquiry(string $trackId): array
    {
        $data = $this->request('GET', '/manage/inquiry/'.rawurlencode($trackId));
        foreach ($data['data'] ?? [] as $item) {
            if ((string) ($item['trackId'] ?? '') === $trackId) {
                return $item;
            }
        }
        throw new RuntimeException('Operation not found in provider result.');
    }

    public function info(string $domain): array
    {
        $response = $this->request('GET', '/domain/'.$domain.'/info');
        $data = $response['data'] ?? null;
        if (! is_array($data) || ($data['domain'] ?? null) !== $domain
            || ! is_string($data['expire_at'] ?? null)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['expire_at'])
            || ! is_numeric($data['id'] ?? null) || (int) $data['id'] <= 0) {
            throw new RuntimeException('Domain ownership or expiry could not be confirmed.');
        }

        [$year, $month, $day] = array_map('intval', explode('-', $data['expire_at']));
        if (! checkdate($month, $day, $year)) {
            throw new RuntimeException('Invalid domain expiry date.');
        }

        return $data;
    }
}
