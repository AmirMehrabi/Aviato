<?php

namespace App\Services\Domains;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

class DomainProviderBudget
{
    public function store()
    {
        $store = config('domain-search.store');
        if (! app()->environment('testing') && in_array(config('cache.stores.'.$store.'.driver'), ['array', 'null', 'octane', 'failover'], true)) {
            throw new \RuntimeException('Domain provider budgets require a persistent shared cache.');
        }

        return Cache::store($store);
    }

    public function reserve(string $provider, bool $search = false): void
    {
        $cache = $this->store();
        try {
            $cache->lock('domains.budget.'.$provider.'.lock', 5)->block(1, function () use ($cache, $provider, $search): void {
                $now = now()->getTimestampMs() / 1000;
                $until = $cache->get('domains.cooldown.'.$provider, 0);
                if ($until > $now) {
                    throw new DomainProviderBusy((int) ceil($until - $now));
                }
                $key = 'domains.budget.'.$provider;
                $calls = array_values(array_filter($cache->get($key, []), fn ($call) => $call['at'] > $now - 60));
                $limit = max(1, (int) config('domain-search.'.$provider.'.per_minute'));
                $searchLimit = max(0, min($limit - 1, (int) config('domain-search.'.$provider.'.search_per_minute')));
                // Search calls can consume only part of the account-wide budget.
                if (count($calls) >= $limit || ($search && count(array_filter($calls, fn ($call) => $call['search'])) >= $searchLimit)) {
                    throw new DomainProviderBusy($calls ? max(1, (int) ceil($calls[0]['at'] + 60 - $now)) : 60);
                }
                $recent = array_values(array_filter($calls, fn ($call) => $call['at'] > $now - 1));
                if (count($recent) >= max(1, (int) config('domain-search.'.$provider.'.burst'))) {
                    throw new DomainProviderBusy(1);
                }
                $calls[] = ['at' => $now, 'search' => $search];
                $cache->put($key, $calls, 61);
            });
        } catch (LockTimeoutException) {
            throw new DomainProviderBusy(2);
        }
    }

    public function observe(string $provider, Response $response): void
    {
        if ($response->status() !== 429) {
            return;
        }
        $header = $response->header('Retry-After');
        $seconds = is_numeric($header) ? (int) ceil((float) $header) : max(0, (strtotime($header ?: '') ?: 0) - now()->timestamp);
        $seconds = max(1, $seconds ?: 60);
        $cache = $this->store();
        // Atomic max: concurrent responses may extend but never shorten a pause.
        $cache->lock('domains.budget.'.$provider.'.lock', 5)->block(1, function () use ($cache, $provider, $seconds): void {
            $until = max($cache->get('domains.cooldown.'.$provider, 0), now()->timestamp + $seconds);
            $cache->put('domains.cooldown.'.$provider, $until, max(1, (int) ceil($until - now()->timestamp)));
        });
        throw new DomainProviderBusy($seconds, true);
    }
}
