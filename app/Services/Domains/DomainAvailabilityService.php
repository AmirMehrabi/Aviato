<?php

namespace App\Services\Domains;

use App\Models\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DomainAvailabilityService
{
    public const BASE_URL = 'https://api.reseller.world/v1.5/multi_registrar';

    /** @return array{domain: string, available: bool, premium: bool|null, checked_at: string} */
    public function check(string $domain): array
    {
        if (str_ends_with($domain, '.ir')) {
            return app(IrnicWhoisService::class)->check($domain);
        }

        $token = AppSetting::domainResellerToken();
        if ($token === '') {
            throw new RuntimeException('Domain provider is not configured.');
        }

        // Cache only validated results briefly. Access is checked before every lookup.
        return Cache::remember('domains.availability.v2.'.hash('sha256', $token.'|'.$domain), 30, function () use ($domain, $token): array {
            try {
                $response = Http::baseUrl(self::BASE_URL)->withToken($token)->acceptJson()
                    ->connectTimeout(5)->timeout(12)->withoutRedirecting()
                    ->post('/domain/'.$domain.'/check', ['period' => 1, 'type' => 'register']);
            } catch (ConnectionException) {
                throw new RuntimeException('Domain provider is unavailable.');
            }

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
            return [
                'domain' => $domain,
                'available' => $data['available'],
                'premium' => $data['premium'] ?? null,
                'checked_at' => now()->toIso8601String(),
            ];
        });
    }
}
