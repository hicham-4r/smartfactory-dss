<?php

use Laravel\Fortify\Features;

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication guard
    |--------------------------------------------------------------------------
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Password broker
    |--------------------------------------------------------------------------
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Login identifier
    |--------------------------------------------------------------------------
    */

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Successful authentication redirect
    |--------------------------------------------------------------------------
    */

    'home' => '/dashboard',

    /*
    |--------------------------------------------------------------------------
    | Fortify routes
    |--------------------------------------------------------------------------
    */

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Rate limiters
    |--------------------------------------------------------------------------
    */

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'passkeys' => 'passkeys',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Blade views
    |--------------------------------------------------------------------------
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Passkeys
    |--------------------------------------------------------------------------
    |
    | Passkeys remain disabled in the feature list.
    |
    */

    'passkeys' => [
        'relying_party_id' => parse_url(
            config('app.url'),
            PHP_URL_HOST
        ),

        'allowed_origins' => [
            config('app.url'),
        ],

        'timeout' => 60000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled features
    |--------------------------------------------------------------------------
    |
    | Registration remains disabled.
    |
    | Two-factor activation requires:
    | - confirmation of the current password;
    | - confirmation of a valid authenticator code.
    |
    */

    'features' => [
        Features::resetPasswords(),

        Features::updatePasswords(),

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];