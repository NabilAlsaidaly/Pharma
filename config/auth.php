<?php

use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;

return [
    'defaults' => ['guard' => 'customer'],
    'guards' => [
        'customer' => ['driver' => 'session', 'provider' => 'customers'],
    ],
    'providers' => [
        'customers' => ['driver' => 'eloquent', 'model' => Customer::class],
    ],
    'passwords' => [],
];
