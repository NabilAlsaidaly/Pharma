<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

return [
    'stateful' => array_values(array_filter(array_map('trim', explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))))),
    'guard' => ['customer'],
    'expiration' => null,
    'middleware' => [
        // Password-hash session invalidation is not applicable to Google-only customers.
        'authenticate_session' => null,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => PreventRequestForgery::class,
    ],
];
