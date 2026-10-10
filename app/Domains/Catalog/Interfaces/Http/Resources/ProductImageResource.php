<?php

namespace App\Domains\Catalog\Interfaces\Http\Resources;

use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use App\Domains\Catalog\Interfaces\Http\CatalogImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductImage */
class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'url' => CatalogImageUrl::forPath($this->path), 'is_primary' => $this->is_primary, 'sort_order' => $this->sort_order];
    }
}
