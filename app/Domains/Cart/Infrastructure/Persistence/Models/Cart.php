<?php

namespace App\Domains\Cart\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $version */
class Cart extends Model
{
    use HasUuids;

    protected $attributes = ['version' => '0'];

    protected $fillable = ['customer_id'];

    protected function casts(): array
    {
        return ['version' => 'string'];
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
