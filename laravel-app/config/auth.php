<?php

use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env(
            'AUTH_GUARD',
            'web'
        ),

        'passwords' => env(
            'AUTH_PASSWORD_BROKER',
            'users'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',

            'model' => env(
                'AUTH_MODEL',
                User::class
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password-reset configuration
    |--------------------------------------------------------------------------
    |
    | Reset tokens expire after 60 minutes.
    | A user must wait 60 seconds before requesting another token.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',

            'table' => env(
                'AUTH_PASSWORD_RESET_TOKEN_TABLE',
                'password_reset_tokens'
            ),

            'expire' => 60,

            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password-confirmation timeout
    |--------------------------------------------------------------------------
    |
    | Sensitive administrative actions require password confirmation.
    | Confirmation remains valid for 15 minutes.
    |
    */

    'password_timeout' => (int) env(
        'AUTH_PASSWORD_TIMEOUT',
        900
    ),
];