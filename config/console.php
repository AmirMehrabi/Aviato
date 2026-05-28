<?php

return [
    'session_ttl' => (int) env('CONSOLE_SESSION_TTL', 60),
    'websockify' => [
        'public_path' => env('CONSOLE_WEBSOCKIFY_PUBLIC_PATH', '/console-ws'),
        'token_file' => env('CONSOLE_WEBSOCKIFY_TOKEN_FILE', '/etc/aviato-console/tokens'),
        'target_host' => env('CONSOLE_WEBSOCKIFY_TARGET_HOST', '127.0.0.1'),
        'ssh_user' => env('CONSOLE_WEBSOCKIFY_SSH_USER', 'root'),
        'ssh_port' => (int) env('CONSOLE_WEBSOCKIFY_SSH_PORT', 22),
        'ssh_key' => env('CONSOLE_WEBSOCKIFY_SSH_KEY'),
        'ssh_connect_timeout' => (int) env('CONSOLE_WEBSOCKIFY_SSH_CONNECT_TIMEOUT', 8),
    ],
];
