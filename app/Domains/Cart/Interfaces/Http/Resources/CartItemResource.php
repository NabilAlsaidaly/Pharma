<?php

namespace App\Domains\Cart\Interfaces\Http\Resources;

use App\Domains\Catalog\Interfaces\Http\CatalogImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = $this->resource;
        if ($result['product'] !== null) {
            $image = $result['product']['primary_image'];
            $result['product']['primary_image'] = $image === null ? null : ['id' => $image['id'],
                'url' => CatalogImageUrl::forPath($image['path']), 'is_primary' => (bool) $image['is_primary'], 'sort_order' => (int) $image['sort_order']];
        }

        return $result;
    }
}
