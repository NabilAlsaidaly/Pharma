<?php

namespace App\Domains\Catalog\Infrastructure\Persistence\Models;

use App\Domains\Catalog\Domain\Enums\AvailabilityStatus;
use App\Domains\Catalog\Domain\Exceptions\ConflictingPriceOwnership;
use App\Domains\Catalog\Infrastructure\Persistence\Concerns\HasCatalogPrices;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property AvailabilityStatus $availability_status */
class ProductVariant extends Model
{
    use HasCatalogPrices, HasUuids;

    protected $fillable = ['product_id', 'name', 'price_usd', 'price_syp', 'availability_status', 'sort_order'];

    protected function casts(): array
    {
        return ['availability_status' => AvailabilityStatus::class, 'sort_order' => 'integer', 'price_usd' => 'decimal:2', 'price_syp' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options) {
            $ids = array_filter([$this->product_id, $this->getRawOriginal('product_id')]);
            // Every writer locks the same parent before inspecting price ownership.
            $parents = Product::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            $parent = $parents->firstWhere('id', $this->product_id);
            if ($parent && ($parent->price_usd !== null || $parent->price_syp !== null)) {
                throw new ConflictingPriceOwnership('Clear parent prices before adding variants.');
            }

            return parent::save($options);
        });
    }
}
