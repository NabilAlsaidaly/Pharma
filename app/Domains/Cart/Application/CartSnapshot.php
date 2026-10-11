<?php

namespace App\Domains\Cart\Application;

use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use Illuminate\Support\Facades\DB;

class CartSnapshot
{
    public function __construct(private readonly CatalogSelectionReader $catalog, private readonly CartEvaluator $evaluator) {}

    public function complete(string $customerId): array
    {
        [$cart, $rows, $products] = DB::transaction(function () use ($customerId) {
            // Non-locking reads share one REPEATABLE READ snapshot, starting with the Cart.
            $cart = Cart::query()->where('customer_id', $customerId)->first();
            $limit = (int) config('customer_collections.cart_selection_limit');
            $rows = $cart?->items()->orderByDesc('created_at')->orderBy('id')->limit($limit + 1)->get()->toArray() ?? [];
            if (count($rows) > $limit) {
                throw new CartConflict('cart_evaluation_capacity_exceeded');
            }
            $products = $this->catalog->read(array_column($rows, 'product_id'), array_column($rows, 'variant_id'));

            return [$cart, $rows, $products];
        });
        $items = array_map(fn (array $row) => $this->evaluator->item($row, $products[$row['product_id']] ?? null), $rows);

        return ['id' => $cart?->id, 'version' => $cart->version ?? '0', 'item_count' => count($items),
            'items' => $items, 'totals' => $this->evaluator->totals($items)];
    }

    public function read(string $customerId, int $page, int $perPage): array
    {
        [$cart, $rows, $products] = DB::transaction(function () use ($customerId) {
            $cart = Cart::query()->where('customer_id', $customerId)->first();
            $rows = $cart?->items()->orderByDesc('created_at')->orderBy('id')->get()->toArray() ?? [];
            $products = $this->catalog->read(array_column($rows, 'product_id'), array_column($rows, 'variant_id'));

            return [$cart, $rows, $products];
        });
        $items = array_map(fn (array $row) => $this->evaluator->item($row, $products[$row['product_id']] ?? null), $rows);

        return ['id' => $cart?->id, 'version' => $cart->version ?? '0', 'evaluated_at' => now()->toISOString(), 'item_count' => count($items),
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage), 'totals' => $this->evaluator->totals($items),
            'requires_request_count' => count(array_filter($items, fn (array $item) => $item['evaluation']['requires_request'] ?? false))];
    }
}
