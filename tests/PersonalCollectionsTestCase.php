<?php

namespace Tests;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;

abstract class PersonalCollectionsTestCase extends CatalogTestCase
{
    protected Customer $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->customer();
        $this->actingAs($this->owner, 'customer');
        $this->withHeader('Origin', 'https://frontend.test');
    }

    protected function otherCustomer(): Customer
    {
        return Customer::query()->create(['google_sub' => 'other-sub', 'name' => 'Other', 'email' => 'other@example.test']);
    }

    protected function addItem($product, ?string $variant = null, int $quantity = 1): array
    {
        return app(CartOperations::class)->add($this->owner->id, $product->id, $variant, $quantity);
    }
}
