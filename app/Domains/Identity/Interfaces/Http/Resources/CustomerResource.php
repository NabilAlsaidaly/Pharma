<?php

namespace App\Domains\Identity\Interfaces\Http\Resources;

use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'account_status' => $this->account_status->value,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
