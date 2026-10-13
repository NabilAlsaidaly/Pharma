<?php

namespace App\Domains\Orders\Interfaces\Http\Resources;

use App\Domains\Orders\Infrastructure\Persistence\Models\CheckoutIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CheckoutIntent */
class PreliminaryRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'status' => $this->status, 'currency' => $this->currency,
            'item_count' => $this->items_count, 'requires_owner_review' => true, 'payment_required_now' => null,
            'payment_instructions_available' => false, 'is_confirmed_order' => false, 'reserves_inventory' => false,
            'created_at' => $this->created_at->toISOString()];
        if ($this->resource->relationLoaded('items')) {
            $data['source_cart_version'] = $this->source_cart_version;
            $data['contact'] = ['full_name' => $this->full_name, 'phone' => $this->phone, 'email' => $this->email,
                'governorate' => $this->governorate, 'city' => $this->city, 'address' => $this->address, 'notes' => $this->notes];
            $data['items'] = $this->items->map(fn ($item) => ['id' => $item->id, 'source_cart_item_id' => $item->source_cart_item_id,
                'product_id' => $item->product_id, 'variant_id' => $item->variant_id, 'quantity' => $item->quantity,
                'requires_fulfillment_review_at_submission' => $item->requires_fulfillment_review, 'line_order' => $item->line_order])->all();
        }

        return $data;
    }
}
