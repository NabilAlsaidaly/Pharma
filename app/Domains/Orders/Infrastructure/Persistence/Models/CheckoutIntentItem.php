<?php

namespace App\Domains\Orders\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CheckoutIntentItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['checkout_intent_id', 'source_cart_item_id', 'product_id', 'variant_id', 'quantity',
        'requires_fulfillment_review', 'line_order'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'line_order' => 'integer', 'requires_fulfillment_review' => 'boolean'];
    }
}
