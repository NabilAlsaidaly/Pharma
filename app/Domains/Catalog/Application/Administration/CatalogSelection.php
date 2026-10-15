<?php

namespace App\Domains\Catalog\Application\Administration;

use App\Domains\ManagementDashboard\Application\SearchTerm;
use Illuminate\Database\Eloquent\Builder;

final class CatalogSelection
{
    public function query(string $type, array $filters): Builder
    {
        $query = CatalogRecords::model($type)->newQuery();
        if (($filters['archived'] ?? false)) {
            $query->onlyTrashed();
        }
        if (! empty($filters['q'])) {
            $term = SearchTerm::pattern($filters['q']);
            $columns = $type === 'images' ? ['id'] : (in_array($type, ['products', 'brands', 'concerns'], true) ? ['name', 'slug'] : ['name']);
            $query->where(function ($where) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $where->orWhereRaw($column." LIKE ? ESCAPE '!'", [$term]);
                }
            });
        }
        foreach (['status' => 'status', 'brand' => 'brand_id', 'availability' => 'availability_status', 'parent' => 'product_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                abort_unless(match ($filter) {
                    'status' => in_array($type, ['products', 'brands', 'concerns'], true),
                    'brand' => $type === 'products', 'availability' => in_array($type, ['products', 'variants'], true),
                    'parent' => in_array($type, ['variants', 'images'], true),
                }, 422);
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['concern'])) {
            abort_unless($type === 'products', 422);
            // Match the Owner listing's retained pivot filter, including an archived Concern's existing links.
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('concern_product')->whereColumn('product_id', 'products.id')->where('concern_id', $filters['concern']));
        }

        return $query;
    }
}
