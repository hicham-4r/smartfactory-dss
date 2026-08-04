<?php

use Illuminate\Support\Str;

return [
    /*
    |--------------------------------------------------------------------------
    | Session driver
    |--------------------------------------------------------------------------
    |
    | Database sessions allow administrators to revoke all active sessions
    | belonging to a deactivated user or a user whose password was reset.
    |
    */

    'driver' => env(
        'SESSION_DRIVER',
        'database'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session lifetime
    |--------------------------------------------------------------------------
    |
    | Sessions expire after 60 minutes of inactivity.
    |
    */

    'lifetime' => (int) env(
        'SESSION_LIFETIME',
        60
    ),

    'expire_on_close' => env(
        'SESSION_EXPIRE_ON_CLOSE',
        false
    ),

    /*
    |--------------------------------------------------------------------------
    | Session encryption
    |--------------------------------------------------------------------------
    |
    | Session data is encrypted before being stored.
    |
    */

    'encrypt' => env(
        'SESSION_ENCRYPT',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | File-session location
    |--------------------------------------------------------------------------
    |
    | Used only when SESSION_DRIVER=file.
    |
    */

    'files' => storage_path(
        'framework/sessions'
    ),

    /*
    |--------------------------------------------------------------------------
    | Database or Redis connection
    |--------------------------------------------------------------------------
    */

    'connection' => env(
        'SESSION_CONNECTION'
    ),

    /*
    |--------------------------------------------------------------------------
    | Database session table
    |--------------------------------------------------------------------------
    */

    'table' => env(
        'SESSION_TABLE',
        'sessions'
    ),

    /*
    |--------------------------------------------------------------------------
    | Cache-backed session store
    |--------------------------------------------------------------------------
    |
    | Used by Redis, Memcached and DynamoDB session drivers.
    |
    */

    'store' => env(
        'SESSION_STORE'
    ),

    /*
    |--------------------------------------------------------------------------
    | Expired-session cleanup lottery
    |--------------------------------------------------------------------------
    */

    'lottery' => [
        2,
        100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session cookie name
    |--------------------------------------------------------------------------
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug(
            (string) env(
                'APP_NAME',
                'laravel'
            )
        ).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session cookie path and domain
    |--------------------------------------------------------------------------
    */

    'path' => env(
        'SESSION_PATH',
        '/'
    ),

    'domain' => env(
        'SESSION_DOMAIN'
    ),

    /*
    |--------------------------------------------------------------------------
    | HTTPS-only cookie
    |--------------------------------------------------------------------------
    |
    | The browser sends the session cookie only over HTTPS.
    |
    */

    'secure' => env(
        'SESSION_SECURE_COOKIE',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | HTTP-only cookie
    |--------------------------------------------------------------------------
    |
    | JavaScript cannot read the session cookie.
    |
    */

    'http_only' => env(
        'SESSION_HTTP_ONLY',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | SameSite cookie protection
    |--------------------------------------------------------------------------
    |
    | Lax helps mitigate cross-site request-forgery attacks while preserving
    | normal application navigation.
    |
    */

    'same_site' => env(
        'SESSION_SAME_SITE',
        'lax'
    ),

    /*
    |--------------------------------------------------------------------------
    | Partitioned cookies
    |--------------------------------------------------------------------------
    |
    | Not required for this same-site DSS application.
    |
    */

    'partitioned' => env(
        'SESSION_PARTITIONED_COOKIE',
        false
    ),
];