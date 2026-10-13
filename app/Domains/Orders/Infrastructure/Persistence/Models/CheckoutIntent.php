<?php

namespace App\Domains\Orders\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckoutIntent extends Model
{
    use HasUuids;

    protected $fillable = ['customer_id', 'cart_id', 'source_cart_version', 'operation_id', 'request_fingerprint',
        'currency', 'status', 'full_name', 'phone', 'email', 'governorate', 'city', 'address', 'notes'];

    protected function casts(): array
    {
        return ['source_cart_version' => 'string'];
    }

    /** @return HasMany<CheckoutIntentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CheckoutIntentItem::class)->orderBy('line_order');
    }
}
