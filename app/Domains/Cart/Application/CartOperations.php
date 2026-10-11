<?php

namespace App\Domains\Cart\Application;

use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Cart\Domain\Rules\CartQuantity;
use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Cart\Infrastructure\Persistence\Models\CartItem;
use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartOperations
{
    public function __construct(private readonly CatalogSelectionReader $catalog, private readonly CartEvaluator $evaluator) {}

    private function lockCustomer(string $id): void
    {
        Customer::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function conflict(string $reason): never
    {
        throw new CartConflict($reason);
    }

    private function advance(Cart $cart): void
    {
        $next = BigInteger::of($cart->version)->plus(1);
        if ($next->isGreaterThan('18446744073709551615')) {
            $this->conflict('cart_version_overflow');
        }
        $cart->version = (string) $next;
        $cart->save();
    }

    private function quantitySum(int $a, int $b): int
    {
        try {
            return CartQuantity::sum($a, $b);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['quantity' => ['Quantity exceeds the technical integer bound.']]);
        }
    }

    private function admission(string $productId, ?string $variantId, int $quantity, array $products): array
    {
        $product = $products[$productId] ?? null;
        abort_if($product === null || ! $product['visible'], 404);
        $value = $this->evaluator->item(['id' => '', 'product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $quantity], $product);
        if (in_array('selection_invalid', $value['evaluation']['reasons'], true)) {
            throw ValidationException::withMessages(['variant_id' => ['Select a valid variant belonging to this product.']]);
        }
        if ($value['evaluation']['state'] === 'blocked') {
            $this->conflict('selection_not_admissible');
        }

        return $value;
    }

    private function response(Cart $cart, CartItem $item, array $products, bool $created = false, bool $merged = false, ?string $removed = null): array
    {
        return ['item' => $this->evaluator->item($item->toArray(), $products[$item->product_id] ?? null),
            'cart_version' => $cart->version, 'created' => $created, 'merged' => $merged, 'removed_item_id' => $removed];
    }

    public function add(string $customerId, string $productId, ?string $variantId, int $quantity): array
    {
        return DB::transaction(function () use ($customerId, $productId, $variantId, $quantity) {
            $this->lockCustomer($customerId);
            $cart = Cart::query()->where('customer_id', $customerId)->lockForUpdate()->first();
            $products = $this->catalog->read([$productId], [$variantId], true);
            $this->admission($productId, $variantId, $quantity, $products);
            $cart ??= Cart::query()->create(['customer_id' => $customerId]);
            $item = $cart->items()->where('product_id', $productId)->where('variant_id', $variantId)->lockForUpdate()->first();
            $created = $item === null;
            if ($created) {
                if ($cart->items()->count() >= config('customer_collections.cart_selection_limit')) {
                    $this->conflict('cart_capacity_reached');
                }
                $item = $cart->items()->create(['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $quantity]);
            } else {
                $item->quantity = $this->quantitySum($item->quantity, $quantity);
                $item->save();
            }
            $this->advance($cart);

            return $this->response($cart, $item, $products, $created);
        }, 3);
    }

    public function update(string $customerId, string $itemId, array $input): array
    {
        return DB::transaction(function () use ($customerId, $itemId, $input) {
            $this->lockCustomer($customerId);
            $cart = Cart::query()->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
            $item = $cart->items()->whereKey($itemId)->firstOrFail();
            if ($cart->version !== $input['expected_version']) {
                $this->conflict('stale_cart_version');
            }
            $target = array_key_exists('variant_id', $input) ? $input['variant_id'] : $item->variant_id;
            $products = $this->catalog->read([$item->product_id], [$item->variant_id, $target], true);
            // Cart aggregate lock serializes all item writers; lock occupied destination in UUID order.
            $items = $cart->items()->where('product_id', $item->product_id)->orderBy('id')->lockForUpdate()->get();
            $item = $items->firstWhere('id', $itemId);
            if (isset($input['quantity'])) {
                if ($input['quantity'] > $item->quantity) {
                    if (! ($products[$item->product_id]['visible'] ?? false)) {
                        $this->conflict('selection_inaccessible');
                    } $this->admission($item->product_id, $target, $input['quantity'], $products);
                }
                if ($input['quantity'] !== $item->quantity) {
                    $item->quantity = $input['quantity'];
                    $item->save();
                    $this->advance($cart);
                }

                return $this->response($cart, $item, $products);
            }
            $current = $products[$item->product_id] ?? null;
            if ($current === null || ! $current['visible']) {
                $this->conflict('selection_inaccessible');
            }
            $this->admission($item->product_id, $target, $item->quantity, $products);
            if ($target === $item->variant_id) {
                return $this->response($cart, $item, $products);
            }
            $destination = $items->first(fn (CartItem $row) => $row->variant_id === $target);
            $removed = null;
            if ($destination) {
                $destination->quantity = $this->quantitySum($destination->quantity, $item->quantity);
                $destination->save();
                $removed = $item->id;
                $item->delete();
                $item = $destination;
            } else {
                $item->variant_id = $target;
                $item->save();
            }
            $this->advance($cart);

            return $this->response($cart, $item, $products, false, $removed !== null, $removed);
        }, 3);
    }

    public function remove(string $customerId, string $itemId): array
    {
        return DB::transaction(function () use ($customerId, $itemId) {
            $this->lockCustomer($customerId);
            $cart = Cart::query()->where('customer_id', $customerId)->lockForUpdate()->first();
            $item = $cart?->items()->whereKey($itemId)->lockForUpdate()->first();
            if ($item) {
                $item->delete();
                $this->advance($cart);
            }

            return ['removed' => true, 'cart_version' => $cart->version ?? '0'];
        }, 3);
    }
}
