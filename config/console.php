<?php

return [
    'session_ttl' => (int) env('CONSOLE_SESSION_TTL', 60),
    'proxy_path' => env('CONSOLE_PROXY_PATH', '/console-ws'),
];
