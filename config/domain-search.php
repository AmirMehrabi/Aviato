<?php

return [
    // Local safety budgets, NOT confirmed provider allowances. All app servers
    // must share this cache store; never use array/null for deployed traffic.
    'store' => env('DOMAIN_PROVIDER_CACHE_STORE', env('CACHE_STORE', 'database')),
    'reseller_world' => [
        'per_minute' => (int) env('DOMAIN_PROVIDER_REQUESTS_PER_MINUTE', 30),
        'search_per_minute' => (int) env('DOMAIN_PROVIDER_SEARCHES_PER_MINUTE', 15),
        'burst' => (int) env('DOMAIN_PROVIDER_REQUESTS_PER_SECOND', 10),
    ],
    'irpower' => [
        'per_minute' => (int) env('DOMAIN_IRPOWER_REQUESTS_PER_MINUTE', 30),
        'search_per_minute' => 0,
        'burst' => (int) env('DOMAIN_IRPOWER_REQUESTS_PER_SECOND', 10),
    ],
    'irnic' => [
        'per_minute' => (int) env('DOMAIN_IRNIC_REQUESTS_PER_MINUTE', 30),
        'search_per_minute' => (int) env('DOMAIN_IRNIC_SEARCHES_PER_MINUTE', 20),
        'burst' => 5,
    ],
];
