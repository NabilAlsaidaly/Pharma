<?php

namespace App\Domains\Cart\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasUuids;

    protected $fillable = ['cart_id', 'product_id', 'variant_id', 'quantity'];

    protected $hidden = ['selection_key'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
