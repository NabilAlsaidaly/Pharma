<?php

namespace App\Domains\Catalog\Infrastructure\Persistence\Models;

use App\Domains\Catalog\Domain\Enums\CatalogStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Concern extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['status' => CatalogStatus::class, 'sort_order' => 'integer'];
    }

    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', CatalogStatus::Active->value);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'concern_product');
    }
}
