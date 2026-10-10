<?php

return [
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),
    'google_auth_rate_limit_per_minute' => max(1, (int) env('GOOGLE_AUTH_RATE_LIMIT_PER_MINUTE', 20)),
];
