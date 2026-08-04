<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication security
    |--------------------------------------------------------------------------
    */

    'authentication' => [
        'max_failed_attempts' => (int) env(
            'AUTH_MAX_FAILED_ATTEMPTS',
            5
        ),

        'lockout_minutes' => (int) env(
            'AUTH_LOCKOUT_MINUTES',
            15
        ),

        'login_attempts_per_minute' => (int) env(
            'AUTH_LOGIN_ATTEMPTS_PER_MINUTE',
            5
        ),

        'login_attempts_per_ip_per_minute' => (int) env(
            'AUTH_LOGIN_ATTEMPTS_PER_IP_PER_MINUTE',
            20
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser security headers
    |--------------------------------------------------------------------------
    */

    'headers' => [
        /*
         * One year. Used only in production over HTTPS.
         */
        'hsts_max_age' => (int) env(
            'SECURITY_HSTS_MAX_AGE',
            31536000
        ),

        /*
         * Disable browser access to hardware and sensitive capabilities
         * that SmartFactory DSS does not require.
         */
        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'accelerometer=(), autoplay=(), camera=(), '
            .'display-capture=(), geolocation=(), gyroscope=(), '
            .'magnetometer=(), microphone=(), payment=(), usb=()'
        ),
    ],
];