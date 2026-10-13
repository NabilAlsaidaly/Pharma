<?php

namespace App\Domains\ManagementIdentity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** @property string $credential_version */
class ManagementUser extends Authenticatable
{
    use HasUuids;

    protected $guarded = ['*'];

    protected $hidden = ['password', 'credential_version', 'owner_slot'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'credential_version' => 'string', 'last_login_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->getOriginal('role') === 'owner' && $user->isDirty('role')) {
                throw new \LogicException('Owner demotion is not supported.');
            }
        });
        static::deleting(function (self $user): void {
            if ($user->role === 'owner' || $user->getOriginal('role') === 'owner') {
                throw new \LogicException('Owner deletion is not supported.');
            }
        });
    }
}
