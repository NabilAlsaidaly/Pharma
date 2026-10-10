<?php

namespace App\Domains\Identity\Infrastructure\Persistence\Models;

use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property CustomerAccountStatus $account_status
 * @property CarbonImmutable|null $last_login_at
 */
class Customer extends Authenticatable
{
    use HasUuids;

    protected $fillable = ['google_sub', 'name', 'email', 'avatar_url', 'account_status', 'last_login_at'];

    protected $hidden = ['google_sub'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'account_status' => CustomerAccountStatus::class,
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
