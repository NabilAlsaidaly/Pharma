<?php

namespace App\Domains\Gifts\Interfaces\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** PublishedGifts supplies the explicit public projection, never a persistence model. */
final class GiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
