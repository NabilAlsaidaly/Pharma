<?php

namespace App\Domains\Orders\Application;

use App\Domains\Cart\Application\CartEvaluator;
use App\Domains\Cart\Application\CartSnapshot;
use App\Domains\Cart\Domain\Exceptions\CartConflict;

class EvaluateCheckout
{
    public function __construct(private readonly CartSnapshot $snapshot, private readonly CartEvaluator $evaluator) {}

    public function execute(string $customerId, string $currency, string $expectedVersion): array
    {
        $snapshot = $this->snapshot->complete($customerId);
        if ($snapshot['version'] !== $expectedVersion) {
            throw new CartConflict('stale_cart_version');
        }
        $items = [];
        $reviews = 0;
        foreach ($snapshot['items'] as $item) {
            $reason = $this->evaluator->exclusionReason($item, $currency);
            $review = $reason === null && ($item['evaluation']['requires_request'] ?? false);
            $reviews += (int) $review;
            $price = $reason === null ? $item['pricing'][$currency] : null;
            $items[] = ['cart_item_id' => $item['id'], 'quantity' => $item['quantity'],
                'unit_price' => $price['unit_price'] ?? null, 'line_estimate' => $price['line_estimate'] ?? null,
                'reasons' => $reason === null ? [] : [$reason], 'requires_fulfillment_review' => $review];
        }
        $totals = $snapshot['totals'][$currency];
        $empty = $snapshot['item_count'] === 0;
        $blocked = $empty || ! $totals['complete'];

        return ['currency' => $currency,
            'cart' => ['id' => $snapshot['id'], 'version' => $snapshot['version'], 'item_count' => $snapshot['item_count']],
            'selection_status' => $blocked ? 'blocked' : ($reviews > 0 ? 'requires_review' : 'valid'),
            'items' => $items,
            'pricing' => ['priced_subtotal' => $totals['priced_subtotal'], 'estimated_items_subtotal' => $totals['estimated_total'],
                'complete' => $totals['complete'], 'excluded_item_count' => $totals['excluded_item_count'], 'exclusion_reasons' => $totals['exclusion_reasons']],
            'blockers' => $empty ? ['empty_cart'] : array_keys((array) $totals['exclusion_reasons']),
            'requires_fulfillment_review_count' => $reviews,
            'commercial' => ['payable_total' => null, 'required_advance' => null, 'delivery_fee' => null,
                'quote_issued' => false, 'payment_instructions_available' => false, 'can_confirm_order' => false, 'reserves_inventory' => false]];
    }
}
