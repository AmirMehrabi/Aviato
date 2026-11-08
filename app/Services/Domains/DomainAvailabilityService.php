<?php

namespace App\Services\Domains;

use App\Models\AppSetting;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DomainAvailabilityService
{
    public const BASE_URL = 'https://api.reseller.world/v1.5/multi_registrar';

    /** @return array{domain: string, available: bool, premium: bool|null, checked_at: string} */
    public function check(string $domain, bool $search = true): array
    {
        if (str_ends_with($domain, '.ir')) {
            return app(IrnicWhoisService::class)->check($domain, $search);
        }

        $token = AppSetting::domainResellerToken();
        if ($token === '') {
            throw new RuntimeException('Domain provider is not configured.');
        }

        // Cache only validated results briefly. Access is checked before every lookup.
        $budget = app(DomainProviderBudget::class);
        $cache = $budget->store();
        $key = 'domains.availability.v2.'.hash('sha256', $token.'|'.$domain);
        if ($cached = $cache->get($key)) {
            return $cached;
        }
        try {
            return $cache->lock($key.'.lock', 20)->block(2, function () use ($cache, $key, $domain, $token, $budget, $search): array {
                if ($cached = $cache->get($key)) {
                    return $cached;
                }
                $budget->reserve('reseller_world', $search);
                try {
                    $response = Http::baseUrl(self::BASE_URL)->withToken($token)->acceptJson()
                        ->connectTimeout(5)->timeout(12)->withoutRedirecting()
                        ->post('/domain/'.$domain.'/check', ['period' => 1, 'type' => 'register']);
                } catch (ConnectionException) {
                    throw new RuntimeException('Domain provider is unavailable.');
                }

                $budget->observe('reseller_world', $response);
                $data = $response->json();
                if (data_get($data, 'error.msgType') === 'msg.notSupportedTld'
                    || ($response->status() === 422 && in_array('Tld is not supported for this reseller.', (array) data_get($data, 'error.details', []), true))) {
                    throw new UnsupportedDomainExtension;
                }
                if (! $response->successful() || ! is_array($data) || ! is_bool($data['available'] ?? null)
                    || (isset($data['premium']) && ! is_bool($data['premium'])) || ($data['domain'] ?? null) !== $domain) {
                    throw new RuntimeException('Domain provider returned an invalid response.');
                }

                // Reseller costs and upstream messages are deliberately not customer quotes.
                $result = [
                    'domain' => $domain,
                    'available' => $data['available'],
                    'premium' => $data['premium'] ?? null,
                    'checked_at' => now()->toIso8601String(),
                ];
                $cache->put($key, $result, 30);

                return $result;
            });
        } catch (LockTimeoutException) {
            throw new DomainProviderBusy(2);
        }
    }
}
