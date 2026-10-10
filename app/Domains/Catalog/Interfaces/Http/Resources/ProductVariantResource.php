<?php

namespace App\Domains\Catalog\Interfaces\Http\Resources;

use App\Domains\Catalog\Domain\Enums\AvailabilityStatus;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductVariant */
class ProductVariantResource extends JsonResource
{
    public function __construct($resource, private readonly AvailabilityStatus $parentAvailability)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'price_usd' => $this->price_usd, 'price_syp' => $this->price_syp, 'availability_status' => $this->availability_status->value, 'effective_availability_status' => $this->parentAvailability->effectiveWith($this->availability_status)->value, 'sort_order' => $this->sort_order];
    }
}
