<?php

return [
    // Only list gateways that replace or safely append the client IP header.
    'proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
];
