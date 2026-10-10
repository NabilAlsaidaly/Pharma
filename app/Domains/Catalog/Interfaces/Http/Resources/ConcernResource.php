<?php

namespace App\Domains\Catalog\Interfaces\Http\Resources;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Concern */
class ConcernResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
    }
}
