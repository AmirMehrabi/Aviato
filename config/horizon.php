<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */

    'use' => env('HORIZON_USE', 'default'),

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    */

    'waits' => [
        'redis:default' => 120,
        'redis:deletions' => 120,
        'redis:provisioning' => 120,
        'redis:backups' => 300,
        'redis:upgrades' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    */

    'silenced' => [
        //
    ],

    'silenced_tags' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'simple',
            'maxTime' => 3600,
            'maxJobs' => 0,
            'processes' => 2,
            'memory' => 256,
            'tries' => 5,
            'timeout' => 900,
            'sleep' => 3,
            'force' => true,
            'nice' => 0,
        ],
        'supervisor-deletions' => [
            'connection' => 'redis',
            'queue' => ['deletions'],
            'balance' => 'simple',
            'maxTime' => 3600,
            'maxJobs' => 0,
            'processes' => 1,
            'memory' => 256,
            'tries' => 5,
            'timeout' => 900,
            'sleep' => 3,
            'force' => true,
            'nice' => 0,
        ],
        'supervisor-provisioning' => [
            'connection' => 'redis',
            'queue' => ['provisioning'],
            'balance' => 'simple',
            'maxTime' => 3600,
            'maxJobs' => 0,
            'processes' => 2,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 600,
            'sleep' => 3,
            'force' => true,
            'nice' => 0,
        ],
        'supervisor-backups' => [
            'connection' => 'redis',
            'queue' => ['backups'],
            'balance' => 'simple',
            'maxTime' => 3600,
            'maxJobs' => 0,
            'processes' => 1,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 1800,
            'sleep' => 3,
            'force' => true,
            'nice' => 0,
        ],
        'supervisor-upgrades' => [
            'connection' => 'redis',
            'queue' => ['upgrades'],
            'balance' => 'simple',
            'maxTime' => 3600,
            'maxJobs' => 0,
            'processes' => 1,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 900,
            'sleep' => 3,
            'force' => true,
            'nice' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    */

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'processes' => (int) env('HORIZON_DEFAULT_PROCESSES', 2),
            ],
            'supervisor-deletions' => [
                'processes' => (int) env('HORIZON_DELETIONS_PROCESSES', 1),
            ],
            'supervisor-provisioning' => [
                'processes' => (int) env('HORIZON_PROVISIONING_PROCESSES', 2),
            ],
            'supervisor-backups' => [
                'processes' => (int) env('HORIZON_BACKUPS_PROCESSES', 1),
            ],
            'supervisor-upgrades' => [
                'processes' => (int) env('HORIZON_UPGRADES_PROCESSES', 1),
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'processes' => 1,
                'memory' => 128,
                'tries' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Dashboard Access
    |--------------------------------------------------------------------------
    */

    'allowed_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => Str::lower(trim($email)),
        explode(',', (string) env('HORIZON_ALLOWED_EMAILS', ''))
    ))),
];
