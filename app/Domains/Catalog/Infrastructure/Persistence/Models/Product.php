<?php

namespace App\Domains\Catalog\Infrastructure\Persistence\Models;

use App\Domains\Catalog\Domain\Enums\AvailabilityStatus;
use App\Domains\Catalog\Domain\Enums\CatalogStatus;
use App\Domains\Catalog\Domain\Exceptions\ConflictingPriceOwnership;
use App\Domains\Catalog\Infrastructure\Persistence\Concerns\HasCatalogPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property AvailabilityStatus $availability_status
 * @property CatalogStatus $status
 * @property string|null $price_usd
 * @property string|null $price_syp
 * @property string|null $usage_instructions
 * @property string|null $suitable_for
 * @property string|null $benefits
 * @property int $variants_count
 * @property bool $has_physical_variants
 * @property string|null $variants_min_price_usd
 * @property string|null $variants_max_price_usd
 * @property string|null $variants_min_price_syp
 * @property string|null $variants_max_price_syp
 */
class Product extends Model
{
    use HasCatalogPrices, HasUuids, SoftDeletes;

    protected $fillable = ['brand_id', 'name', 'slug', 'short_description', 'full_description', 'status', 'availability_status', 'is_recommended', 'usage_instructions', 'suitable_for', 'benefits', 'price_usd', 'price_syp'];

    protected function casts(): array
    {
        return ['status' => CatalogStatus::class, 'availability_status' => AvailabilityStatus::class, 'is_recommended' => 'boolean', 'price_usd' => 'decimal:2', 'price_syp' => 'decimal:2'];
    }

    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('products.status', CatalogStatus::Active->value)->whereHas('brand', fn (Builder $brand) => $brand->where('status', CatalogStatus::Active->value));
    }

    public function scopeWithPricingOwnership(Builder $query): void
    {
        // P1 ownership survives archival; current Variant counts/ranges keep their own scopes.
        $query->withExists(['variants as has_physical_variants' => fn ($variants) => $variants->withTrashed()]);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasOne<ProductImage, $this> */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany(['is_primary' => 'max', 'sort_order' => 'min', 'id' => 'min']);
    }

    /** @return BelongsToMany<Concern, $this> */
    public function concerns(): BelongsToMany
    {
        return $this->belongsToMany(Concern::class, 'concern_product');
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options) {
            if ($this->exists) {
                static::withTrashed()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
                if (($this->price_usd !== null || $this->price_syp !== null) && $this->variants()->withTrashed()->lockForUpdate()->exists()) {
                    throw new ConflictingPriceOwnership('Variant products cannot own parent prices.');
                }
            }

            return parent::save($options);
        });
    }
}
