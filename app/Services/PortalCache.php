<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class PortalCache
{
    /** Cache only presentation data. Callers must authorize before invoking this method. */
    public function remember(string $scope, string $key, int $seconds, Closure $compute): mixed
    {
        if (! config('performance.portal_cache_enabled')) {
            return $compute();
        }

        $lock = null;

        try {
            $store = Cache::store(config('performance.portal_cache_store'));
            $versionKey = 'portal:v1:version:'.$scope;
            $version = $store->get($versionKey);
            if ($version === null) {
                $store->add($versionKey, (string) Str::uuid(), 3600);
                $version = $store->get($versionKey);
            }
            $cacheKey = 'portal:v1:'.$scope.':'.$version.':'.hash('sha256', $key);
            $cached = $store->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $lock = $store->lock($cacheKey.':lock', 30);
            $lock->block(2);
            $cached = $store->get($cacheKey);
            if ($cached !== null) {
                $lock->release();

                return $cached;
            }
        } catch (Throwable $exception) {
            report($exception);
            if ($lock) {
                try {
                    $lock->release();
                } catch (Throwable) {
                    // Optional presentation caching must not break the page.
                }
            }

            return $compute();
        }

        try {
            $value = $compute();
            try {
                $store->put($cacheKey, $value, $seconds);
            } catch (Throwable $exception) {
                report($exception);
            }

            return $value;
        } finally {
            try {
                $lock->release();
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function invalidate(array $scopes = ['admin', 'customer', 'network']): void
    {
        if (! config('performance.portal_cache_enabled')) {
            return;
        }

        try {
            $store = Cache::store(config('performance.portal_cache_store'));
            foreach ($scopes as $scope) {
                $store->forget('portal:v1:version:'.$scope);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
