<?php

return [
    'enabled' => env('CLOUD_DNS_ENABLED', false),
    'nameservers' => ['ns1.aviato.ir', 'ns2.aviato.ir'],
    'primary_ip' => env('CLOUD_DNS_PRIMARY_IP', '172.19.19.12'),
    'secondary_ip' => env('CLOUD_DNS_SECONDARY_IP', '172.19.19.13'),
    'ca_bundle' => env('CLOUD_DNS_CA_BUNDLE'),
    'tsig_key_name' => env('CLOUD_DNS_TSIG_KEY_NAME', 'aviato-transfer'),
    'nodes' => [
        'primary' => ['url' => env('CLOUD_DNS_PRIMARY_URL', 'https://172.19.19.12:8443'), 'key' => env('CLOUD_DNS_PRIMARY_KEY')],
        'secondary' => ['url' => env('CLOUD_DNS_SECONDARY_URL', 'https://172.19.19.13:8443'), 'key' => env('CLOUD_DNS_SECONDARY_KEY')],
    ],
    'plans' => [
        'free' => ['zone_limit' => (int) env('CLOUD_DNS_ZONE_LIMIT', 25), 'record_limit' => (int) env('CLOUD_DNS_RECORD_LIMIT', 500), 'monthly_price' => 0],
    ],
    'reserved_domains' => ['aviato.ir'],
    'verification_hours' => 48,
];
