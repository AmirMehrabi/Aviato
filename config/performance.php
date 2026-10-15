<?php

return [
    'portal_cache_enabled' => env('PORTAL_CACHE_ENABLED', true),
    'portal_cache_store' => env('PORTAL_CACHE_STORE', env('CACHE_STORE', 'database')),
    'admin_ttl' => 15,
    'customer_ttl' => 30,
];
