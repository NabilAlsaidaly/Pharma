<?php

namespace App\Domains\Catalog\Interfaces\Http\Resources;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantPricing = $this->has_physical_variants;
        $pricing = ['source' => $variantPricing ? 'variants' : 'product', 'price_usd' => $variantPricing ? null : $this->price_usd, 'price_syp' => $variantPricing ? null : $this->price_syp];
        if ($variantPricing) {
            $pricing['range_usd'] = ['min' => $this->variants_min_price_usd, 'max' => $this->variants_max_price_usd];
            $pricing['range_syp'] = ['min' => $this->variants_min_price_syp, 'max' => $this->variants_max_price_syp];
        }

        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'short_description' => $this->short_description,
            'brand' => $this->whenLoaded('brand', fn () => ['id' => $this->brand->id, 'name' => $this->brand->name, 'slug' => $this->brand->slug]),
            'availability_status' => $this->availability_status->value, 'is_recommended' => $this->is_recommended,
            'pricing' => $pricing, 'primary_image' => $this->whenLoaded('primaryImage', fn () => $this->primaryImage ? new ProductImageResource($this->primaryImage) : null),
            'full_description' => $this->when($this->resource->relationLoaded('variants'), $this->full_description),
            'usage_instructions' => $this->when($this->resource->relationLoaded('variants'), $this->usage_instructions),
            'suitable_for' => $this->when($this->resource->relationLoaded('variants'), $this->suitable_for),
            'benefits' => $this->when($this->resource->relationLoaded('variants'), $this->benefits),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn (ProductVariant $v) => new ProductVariantResource($v, $this->availability_status))),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'concerns' => ConcernResource::collection($this->whenLoaded('concerns')),
        ];
    }
}
