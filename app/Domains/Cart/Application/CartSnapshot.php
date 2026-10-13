<?php

namespace App\Domains\Cart\Application;

use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use Illuminate\Support\Facades\DB;

class CartSnapshot
{
    public function __construct(private readonly CatalogSelectionReader $catalog, private readonly CartEvaluator $evaluator) {}

    public function lockedForSubmission(string $customerId, string $expectedVersion, string $currency): array
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Submission requires an enclosing Customer-locked transaction.');
        }
        $cart = Cart::query()->where('customer_id', $customerId)->lockForUpdate()->first();
        if (($cart->version ?? '0') !== $expectedVersion) {
            throw new CartConflict('stale_cart_version');
        }
        $limit = (int) config('customer_collections.cart_selection_limit');
        $rows = $cart?->items()->orderByDesc('created_at')->orderBy('id')->limit($limit + 1)->get()->toArray() ?? [];
        if (count($rows) > $limit) {
            throw new CartConflict('cart_evaluation_capacity_exceeded');
        }
        if ($rows === []) {
            throw new CartConflict('empty_cart');
        }
        $products = $this->catalog->read(array_column($rows, 'product_id'), array_column($rows, 'variant_id'), true);
        // Customer/Cart serialize cooperating writers; take selection locks after Catalog locks.
        $locked = $cart->items()->orderBy('id')->limit($limit + 1)->lockForUpdate()->get()->keyBy('id');
        $items = [];
        foreach ($rows as $row) {
            $current = $locked->get($row['id']);
            if ($current === null || $current->product_id !== $row['product_id'] || $current->quantity !== $row['quantity'] || $current->variant_id !== $row['variant_id']) {
                throw new CartConflict('stale_cart_version');
            }
            $evaluation = $this->evaluator->item($row, $products[$row['product_id']] ?? null);
            if ($this->evaluator->exclusionReason($evaluation, $currency) !== null) {
                throw new CartConflict('checkout_selection_blocked');
            }
            $items[] = ['source_cart_item_id' => $row['id'], 'product_id' => $row['product_id'], 'variant_id' => $row['variant_id'],
                'quantity' => $row['quantity'], 'requires_fulfillment_review' => $evaluation['evaluation']['requires_request'] ?? false];
        }
        if ($locked->count() !== count($rows)) {
            throw new CartConflict('stale_cart_version');
        }

        return ['cart_id' => $cart->id, 'version' => $cart->version, 'items' => $items];
    }

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
