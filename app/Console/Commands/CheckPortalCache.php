<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class CheckPortalCache extends Command
{
    protected $signature = 'performance:check-cache';

    protected $description = 'Verify the configured portal cache can read, write and lock';

    public function handle(): int
    {
        if (! config('performance.portal_cache_enabled')) {
            $this->info('Portal caching is disabled.');

            return self::SUCCESS;
        }

        $key = 'portal:healthcheck:'.Str::uuid();
        $store = Cache::store(config('performance.portal_cache_store'));
        $lock = $store->lock($key.':lock', 5);
        try {
            $store->put($key, ['ok' => true], 10);
            if ($store->get($key) !== ['ok' => true] || ! $lock->get()) {
                $this->error('Portal cache read/write/lock check failed.');

                return self::FAILURE;
            }
            $this->info('Portal cache read/write/lock check passed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Portal cache is unavailable ('.$exception::class.').');

            return self::FAILURE;
        } finally {
            try {
                $lock->release();
                $store->forget($key);
            } catch (Throwable) {
                // The check has already failed; its temporary key expires automatically.
            }
        }
    }
}
