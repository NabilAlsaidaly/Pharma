<?php

namespace App\Domains\ManagementDashboard\Infrastructure\ReadModels;

use App\Domains\ManagementDashboard\Application\DashboardDateRange;
use App\Domains\ManagementDashboard\Application\DashboardResources;
use App\Domains\ManagementDashboard\Application\SearchTerm;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class OwnerResourceReader
{
    public function __construct(private DashboardSchema $schema) {}

    private function table(string $resource): string
    {
        abort_unless(isset(DashboardResources::DEFINITIONS[$resource]), 404);

        return DashboardResources::DEFINITIONS[$resource]['table'];
    }

    private function keyword(Builder $query, string $table, string $term): void
    {
        if ($term === '') {
            return;
        }
        if ($table === 'checkout_intents') {
            if (! preg_match('/^[0-9a-f-]{2,36}$/iD', $term)) {
                $query->whereRaw('0 = 1');
            } elseif (strlen($term) === 36) {
                $query->where($table.'.id', strtolower($term));
            } else {
                $query->whereRaw($table.".id LIKE ? ESCAPE '!'", [SearchTerm::pattern(strtolower($term), true)]);
            }

            return;
        }
        $query->where(function (Builder $where) use ($table, $term): void {
            $where->whereRaw($table.".name LIKE ? ESCAPE '!'", [SearchTerm::pattern($term)])
                ->orWhereRaw($table.".slug LIKE ? ESCAPE '!'", [SearchTerm::pattern($term)]);
        });
    }

    public function search(string $resource, string $term): ?array
    {
        $table = $this->table($resource);
        if (! $this->schema->has($table)) {
            return null;
        }
        $columns = $resource === 'requests' ? ['id', 'status', 'currency', 'created_at'] : ['id', 'name', 'slug', 'status'];
        $query = DB::table($table)->select($columns);
        $this->keyword($query, $table, $term);

        return $query->orderBy($resource === 'requests' ? 'created_at' : 'name')->orderBy('id')->limit(10)->get()->all();
    }

    public function listing(string $resource, array $input)
    {
        $table = $this->table($resource);
        $this->schema->require($table);
        $query = DB::table($table);
        if ($resource === 'products') {
            $this->schema->require('brands', 'product_variants');
            $query->join('brands', 'brands.id', '=', 'products.brand_id')->select('products.id', 'products.name', 'products.slug', 'products.status', 'products.availability_status', 'products.created_at', 'brands.name as brand_name')
                ->selectSub(DB::table('product_variants')->selectRaw('COUNT(*)')->whereColumn('product_id', 'products.id'), 'variants_count');
            foreach (['brand' => 'brand_id', 'availability' => 'availability_status'] as $filter => $column) {
                if (isset($input[$filter])) {
                    $query->where('products.'.$column, $input[$filter]);
                }
            }
            if (isset($input['concern'])) {
                $this->schema->require('concern_product');
                $query->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('concern_product')->whereColumn('product_id', 'products.id')->where('concern_id', $input['concern']));
            }
        } elseif ($resource === 'requests') {
            $this->schema->require('checkout_intent_items');
            $query->select('id', 'status', 'currency', 'created_at')
                ->selectSub(DB::table('checkout_intent_items')->selectRaw('COUNT(*)')->whereColumn('checkout_intent_id', 'checkout_intents.id'), 'items_count')
                ->selectSub(DB::table('checkout_intent_items')->selectRaw('COALESCE(MAX(requires_fulfillment_review), 0)')->whereColumn('checkout_intent_id', 'checkout_intents.id'), 'needs_review');
            if (isset($input['currency'])) {
                $query->where('currency', $input['currency']);
            }
            if (isset($input['from'], $input['to'])) {
                $range = DashboardDateRange::from($input + ['period' => 'custom']);
                $query->where('created_at', '>=', $range->start->utc()->format('Y-m-d H:i:s'))->where('created_at', '<', $range->end->utc()->format('Y-m-d H:i:s'));
            }
        } else {
            $this->schema->require('products');
            $query->select('id', 'name', 'slug', 'status', 'created_at');
            $count = DB::table('products')->selectRaw('COUNT(*)')->whereColumn('brand_id', 'brands.id');
            if ($resource === 'concerns') {
                $this->schema->require('concern_product');
                $count = DB::table('concern_product')->selectRaw('COUNT(*)')->whereColumn('concern_id', 'concerns.id');
            }
            $query->selectSub($count, 'products_count');
        }
        $this->keyword($query, $table, $input['q'] ?? '');
        if (isset($input['status'])) {
            $query->where($table.'.status', $input['status']);
        }
        $sort = $input['sort'] ?? ($resource === 'requests' ? 'newest' : 'name');
        [$column, $direction] = match ($sort) {
            'oldest' => ['created_at', 'asc'], 'newest' => ['created_at', 'desc'], default => ['name', 'asc'],
        };

        return $query->orderBy($table.'.'.$column, $direction)->orderBy($table.'.id')->paginate($input['per_page'] ?? 25, ['*'], 'page', $input['page'] ?? 1)->appends($input);
    }

    public function detail(string $resource, string $id, int $page = 1): array
    {
        $table = $this->table($resource);
        $this->schema->require($table);
        if ($resource === 'requests') {
            $this->schema->require('checkout_intent_items');
            $record = DB::table($table)->select('id', 'status', 'currency', 'created_at', 'full_name', 'phone', 'email', 'governorate', 'city', 'address', 'notes')->where('id', $id)->first();
            abort_if($record === null, 404);
            $related = DB::table('checkout_intent_items')->select('id', 'product_id', 'variant_id', 'quantity', 'requires_fulfillment_review', 'line_order')
                ->where('checkout_intent_id', $id)->orderBy('line_order')->orderBy('id')->paginate(25, ['*'], 'page', $page);
        } elseif ($resource === 'products') {
            $this->schema->require('brands', 'product_variants');
            $record = DB::table($table)->join('brands', 'brands.id', '=', 'products.brand_id')->select('products.*', 'brands.name as brand_name')
                ->selectSub(DB::table('product_variants')->selectRaw('COUNT(*)')->whereColumn('product_id', 'products.id'), 'variants_count')->where('products.id', $id)->first();
            abort_if($record === null, 404);
            $related = DB::table('product_variants')->select('id', 'name', 'availability_status', 'price_usd', 'price_syp')->where('product_id', $id)->orderBy('sort_order')->orderBy('id')->paginate(25, ['*'], 'page', $page);
        } else {
            $record = DB::table($table)->select('id', 'name', 'slug', 'status', 'created_at')->where('id', $id)->first();
            abort_if($record === null, 404);
            $this->schema->require('products');
            $query = DB::table('products')->select('products.id', 'products.name', 'products.status', 'products.created_at');
            if ($resource === 'brands') {
                $query->where('brand_id', $id);
            } else {
                $this->schema->require('concern_product');
                $query->join('concern_product', 'product_id', '=', 'products.id')->where('concern_id', $id);
            }
            $related = $query->orderBy('products.name')->orderBy('products.id')->paginate(25, ['*'], 'page', $page);
        }

        return compact('record', 'related');
    }

    public function brandOptions(?string $selected = null): array
    {
        if (! $this->schema->has('brands')) {
            return [];
        }

        $query = DB::table('brands')->select('id', 'name');
        if ($selected !== null) {
            $query->orderByRaw('id = ? DESC', [$selected]);
        }

        return $query->orderBy('name')->orderBy('id')->limit(50)->get()->all();
    }
}
