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
        $data = $this->request('GET', '/domain/'.$domain.'/info');
        if (($data['domain'] ?? null) !== $domain || empty($data['expire_at']) || empty($data['id'])) {
            throw new RuntimeException('Domain ownership or expiry could not be confirmed.');
        }

        return $data;
    }
}
