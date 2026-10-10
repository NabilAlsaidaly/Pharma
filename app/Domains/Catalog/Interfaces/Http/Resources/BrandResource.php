<?php

namespace App\Domains\Catalog\Interfaces\Http\Resources;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Interfaces\Http\CatalogImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Brand */
class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description, 'logo_url' => CatalogImageUrl::forPath($this->logo_path), 'meta_title' => $this->meta_title, 'meta_description' => $this->meta_description];
    }
}
