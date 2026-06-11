<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route prefix & middleware
    |--------------------------------------------------------------------------
    |
    | The package mounts its UI and JSON API under this prefix. The listed
    | middleware run first; the package always layers its own access gate
    | (EnsureLogScopeAccess) on top of whatever is configured here.
    |
    */

    'route_prefix' => env('LOGSCOPE_PREFIX', 'logscope'),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Authentication (zero-config default)
    |--------------------------------------------------------------------------
    |
    | Out of the box access is protected by a single username/password read
    | from the environment. If both are empty and the app is running in the
    | local environment, access MAY be opened (set 'enabled' => false). In
    | any non-local environment missing credentials result in a locked 403.
    |
    */

    'auth' => [
        'enabled'   => env('LOGSCOPE_AUTH_ENABLED', true),
        'user'      => env('LOGSCOPE_AUTH_USER'),
        'password'  => env('LOGSCOPE_AUTH_PASSWORD'),
        'rate_limit' => env('LOGSCOPE_AUTH_RATE_LIMIT', 5), // login attempts per minute per IP
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization override (production path)
    |--------------------------------------------------------------------------
    |
    | An invokable class-string that receives the Request and returns bool.
    | When set (or when LogScope::auth() is called in a service provider) it
    | takes precedence over the env credentials above.
    |
    */

    'authorization' => [
        'using' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | File operations
    |--------------------------------------------------------------------------
    |
    | Master switch for the download / clear / delete endpoints. When false
    | those endpoints respond 403 regardless of authorization.
    |
    */

    'allow_file_operations' => env('LOGSCOPE_ALLOW_FILE_OPS', true),

    /*
    |--------------------------------------------------------------------------
    | Hosts
    |--------------------------------------------------------------------------
    |
    | A map of name => driver config. 'local' reads this server's filesystem
    | and is always present. 'remote' proxies to another LogScope instance
    | (deferred to a later release).
    |
    */

    'hosts' => [
        'local' => ['driver' => 'local', 'label' => 'Local'],
        // 'staging' => [
        //     'driver' => 'remote',
        //     'url'    => env('LOGSCOPE_STAGING_URL'),
        //     'secret' => env('LOGSCOPE_STAGING_SECRET'),
        //     'timeout' => 5,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | A source is a named collection of glob patterns plus the parser used to
    | turn their lines into log entries. Globs are resolved at request time.
    |
    */

    'sources' => [
        'laravel' => [
            'label'   => 'Laravel',
            'paths'   => [storage_path('logs/*.log')],
            'parser'  => 'laravel',
            'enabled' => true,
        ],
        'horizon' => [
            'label'   => 'Horizon',
            'paths'   => [storage_path('logs/horizon*.log')],
            'parser'  => 'horizon',
            'enabled' => false,
        ],
        'nginx' => [
            'label'   => 'Nginx',
            'paths'   => ['/var/log/nginx/access.log', '/var/log/nginx/error.log'],
            'parser'  => 'nginx',
            'enabled' => false,
        ],
        'apache' => [
            'label'   => 'Apache',
            'paths'   => ['/var/log/apache2/*.log'],
            'parser'  => 'apache',
            'enabled' => false,
        ],
        'redis' => [
            'label'   => 'Redis',
            'paths'   => ['/var/log/redis/*.log'],
            'parser'  => 'redis',
            'enabled' => false,
        ],
        'supervisor' => [
            'label'   => 'Supervisor',
            'paths'   => ['/var/log/supervisor/*.log'],
            'parser'  => 'supervisor',
            'enabled' => false,
        ],
        'postgres' => [
            'label'   => 'PostgreSQL',
            'paths'   => ['/var/log/postgresql/*.log'],
            'parser'  => 'postgres',
            'enabled' => false,
        ],
        'php-fpm' => [
            'label'   => 'PHP-FPM',
            'paths'   => ['/var/log/php*-fpm.log'],
            'parser'  => 'generic',
            'enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Levels & aliases
    |--------------------------------------------------------------------------
    |
    | The canonical PSR-3 levels plus a synthetic 'unknown'. Each carries a
    | label and a colour used by the UI. 'level_aliases' maps raw tokens
    | found in logs to one of these canonical keys.
    |
    */

    'levels' => [
        'debug'     => ['label' => 'Debug',     'color' => '#6b7280'],
        'info'      => ['label' => 'Info',      'color' => '#3b82f6'],
        'notice'    => ['label' => 'Notice',    'color' => '#0ea5e9'],
        'warning'   => ['label' => 'Warning',   'color' => '#f59e0b'],
        'error'     => ['label' => 'Error',     'color' => '#ef4444'],
        'critical'  => ['label' => 'Critical',  'color' => '#dc2626'],
        'alert'     => ['label' => 'Alert',     'color' => '#b91c1c'],
        'emergency' => ['label' => 'Emergency', 'color' => '#7f1d1d'],
        'unknown'   => ['label' => 'Unknown',   'color' => '#9ca3af'],
    ],

    'level_aliases' => [
        'warn'    => 'warning',
        'err'     => 'error',
        'crit'    => 'critical',
        'fatal'   => 'critical',
        'panic'   => 'emergency',
        'trace'   => 'debug',
        'fine'    => 'debug',
    ],

    /*
    |--------------------------------------------------------------------------
    | Correlation (execution-context tracking)
    |--------------------------------------------------------------------------
    */

    'correlation' => [
        'store'       => env('LOGSCOPE_CORRELATION_STORE', 'database'), // database|null
        'sample_rate' => (float) env('LOGSCOPE_CORRELATION_SAMPLE', 1.0),
        'capture'     => [
            'http'     => true,
            'queue'    => true,
            'command'  => true,
            'schedule' => true,
        ],
        'prune_hours' => (int) env('LOGSCOPE_PRUNE_HOURS', 72),
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time
    |--------------------------------------------------------------------------
    */

    'realtime' => [
        'poll_ms'      => (int) env('LOGSCOPE_POLL_MS', 3000),
        'broadcasting' => [
            'enabled' => env('LOGSCOPE_BROADCAST', false),
            'channel' => 'logscope',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail preview
    |--------------------------------------------------------------------------
    */

    'mail_preview' => [
        'enabled' => env('LOGSCOPE_MAIL_PREVIEW', true),
    ],

];
