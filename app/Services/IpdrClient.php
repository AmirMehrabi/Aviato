<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IpdrClient
{
    /** @return array<string, mixed> */
    public function buckets(?string $cursor = null, int $limit = 500, ?string $from = null, ?string $to = null): array
    {
        $response = $this->request()->get('/api/v1/usage/buckets', array_filter([
            'cursor' => $cursor,
            'limit' => min(1000, max(1, $limit)),
            'from' => $from,
            'to' => $to,
        ], fn ($value) => $value !== null));

        $response->throw();
        $data = $response->json();

        if (! is_array($data) || ! is_array($data['items'] ?? null)) {
            throw new RuntimeException('IPDR returned an invalid usage page.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function summaries(string $from, string $to, ?string $vmUuid = null): array
    {
        return $this->request()->get('/api/v1/usage/summaries', array_filter([
            'from' => $from, 'to' => $to, 'vm_uuid' => $vmUuid,
        ]))->throw()->json();
    }

    private function request(): PendingRequest
    {
        $url = rtrim((string) config('services.ipdr.url'), '/');
        if ($url === '') {
            throw new RuntimeException('IPDR_URL is not configured.');
        }

        return Http::baseUrl($url)
            ->acceptJson()
            ->withToken((string) config('services.ipdr.token'))
            ->connectTimeout((int) config('services.ipdr.connect_timeout', 5))
            ->timeout((int) config('services.ipdr.timeout', 30))
            ->retry(3, 500, throw: false);
    }
}
