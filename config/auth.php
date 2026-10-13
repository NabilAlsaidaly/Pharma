<?php

use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;

return [
    'defaults' => ['guard' => 'customer'],
    'guards' => [
        'customer' => ['driver' => 'session', 'provider' => 'customers'],
        'owner' => ['driver' => 'session', 'provider' => 'management_users'],
    ],
    'providers' => [
        'customers' => ['driver' => 'eloquent', 'model' => Customer::class],
        'management_users' => ['driver' => 'eloquent', 'model' => ManagementUser::class],
    ],
    'passwords' => [],
];
