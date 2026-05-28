<?php

return [
    'proxy_url' => env('CONSOLE_PROXY_URL', '/console-ws'),
    'proxy_internal_url' => env('CONSOLE_PROXY_INTERNAL_URL', env('APP_URL', 'http://127.0.0.1')),
    'proxy_secret' => env('CONSOLE_PROXY_SECRET'),
    'session_ttl' => (int) env('CONSOLE_SESSION_TTL', 60),
];
