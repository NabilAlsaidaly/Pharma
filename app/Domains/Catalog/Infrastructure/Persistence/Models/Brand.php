<?php

namespace App\Domains\Catalog\Infrastructure\Persistence\Models;

use App\Domains\Catalog\Domain\Enums\CatalogStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CatalogStatus $status */
class Brand extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'logo_path', 'description', 'status', 'sort_order', 'meta_title', 'meta_description'];

    protected function casts(): array
    {
        return ['status' => CatalogStatus::class, 'sort_order' => 'integer'];
    }

    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', CatalogStatus::Active->value);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
