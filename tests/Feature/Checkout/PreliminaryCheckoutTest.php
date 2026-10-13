<?php

namespace Tests\Feature\Checkout;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Application\CartSnapshot;
use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use App\Domains\Identity\Interfaces\Http\Middleware\EnsureActiveCustomer;
use App\Domains\Orders\Infrastructure\Persistence\Models\CheckoutIntent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeGoogleOAuthClient;
use Tests\PersonalCollectionsTestCase;

class PreliminaryCheckoutTest extends PersonalCollectionsTestCase
{
    private const URL = '/api/v1/checkout/intents';

    private function read(string $url)
    {
        return $this->get($url, ['Accept' => 'application/json']);
    }

    private function payload(): array
    {
        return ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1',
            'contact' => ['full_name' => 'سمية أحمد', 'phone' => '+44 (123) 456-789', 'governorate' => 'دمشق',
                'city' => 'دمشق', 'address' => 'شارع التجربة 12']];
    }

    private function seedCart(): void
    {
        $this->addItem($this->product(['price_usd' => '2', 'price_syp' => '100']));
    }

    public function test_creation_summary_detail_and_source_rows_are_unchanged(): void
    {
        $this->seedCart();
        $before = [DB::table('customers')->get()->toArray(), DB::table('carts')->get()->toArray(), DB::table('cart_items')->get()->toArray()];
        $payload = $this->payload();
        $r = $this->postJson(self::URL, $payload)->assertCreated()->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.item_count', 1)->assertJsonPath('data.requires_owner_review', true)
            ->assertJsonPath('data.payment_required_now', null)->assertJsonPath('data.payment_instructions_available', false)
            ->assertJsonPath('data.is_confirmed_order', false)->assertJsonPath('data.reserves_inventory', false);
        $this->assertArrayNotHasKey('contact', $r->json('data'));
        $detail = $this->read(self::URL.'/'.$r->json('data.id'))->assertOk()->assertJsonPath('data.contact.email', $this->owner->email)
            ->assertJsonPath('data.contact.full_name', $payload['contact']['full_name'])->assertJsonPath('data.source_cart_version', '1')->assertJsonCount(1, 'data.items');
        foreach (['price', 'name', 'subtotal', 'total', 'operation_id', 'request_fingerprint'] as $key) {
            $this->assertArrayNotHasKey($key, $detail->json('data'));
            $this->assertArrayNotHasKey($key, $detail->json('data.items.0'));
        }
        $this->assertEquals($before, [DB::table('customers')->get()->toArray(), DB::table('carts')->get()->toArray(), DB::table('cart_items')->get()->toArray()]);
    }

    public function test_replay_after_cart_deletion_catalog_hiding_and_email_change_returns_original(): void
    {
        $this->seedCart();
        $payload = $this->payload();
        $first = $this->postJson(self::URL, $payload)->assertCreated()->json('data');
        $detail = $this->read(self::URL.'/'.$first['id'])->assertOk()->json('data');
        DB::table('cart_items')->delete();
        DB::table('carts')->update(['version' => 2]);
        DB::table('products')->update(['status' => 'hidden', 'price_usd' => null]);
        $this->owner->update(['email' => 'changed@example.test']);
        $this->assertSame($first, $this->postJson(self::URL, $payload)->assertOk()->json('data'));
        $this->assertSame($detail, $this->read(self::URL.'/'.$first['id'])->assertOk()->json('data'));
        $this->assertDatabaseCount('checkout_intents', 1);
        $this->assertDatabaseCount('checkout_intent_items', 1);
    }

    public function test_normalization_omitted_notes_null_notes_and_uuid_case_replay(): void
    {
        $this->seedCart();
        $p = $this->payload();
        $p['operation_id'] = strtoupper((string) Str::uuid());
        $p['contact']['full_name'] = '  '.$p['contact']['full_name'].'  ';
        $first = $this->postJson(self::URL, $p)->assertCreated()->json('data.id');
        $p['operation_id'] = strtolower($p['operation_id']);
        $p['contact']['full_name'] = trim($p['contact']['full_name']);
        $p['contact']['notes'] = null;
        $this->postJson(self::URL, $p)->assertOk()->assertJsonPath('data.id', $first);
    }

    public function test_changed_payload_conflicts_but_distinct_keys_create_multiple_requests(): void
    {
        $this->seedCart();
        $p = $this->payload();
        $this->postJson(self::URL, $p)->assertCreated();
        foreach (['currency', 'expected_cart_version', 'full_name', 'phone', 'governorate', 'city', 'address', 'notes'] as $field) {
            $changed = $p;
            if ($field === 'currency') {
                $changed[$field] = 'SYP';
            } elseif ($field === 'expected_cart_version') {
                $changed[$field] = '2';
            } else {
                $changed['contact'][$field] = $field === 'phone' ? '+1234567' : 'تغيير';
            }
            $this->postJson(self::URL, $changed)->assertConflict();
        }
        $p['operation_id'] = (string) Str::uuid7();
        $this->postJson(self::URL, $p)->assertCreated();
        $this->assertDatabaseCount('checkout_intents', 2);
    }

    public static function invalidFields(): array
    {
        $cases = [];
        foreach (['operation_id' => [null, 7, '', 'not-uuid', '00000000-0000-1000-8000-000000000000'],
            'currency' => [null, 'usd', ' USD', 'EUR', 1],
            'expected_cart_version' => [null, 1, -1, '01', ' 1', '1.0', '18446744073709551616', '1e0'],
            'contact.full_name' => [null, '', '   ', '!!!', ['x'], '<script>x</script>', "a\r\nb", str_repeat('أ', 151)],
            'contact.phone' => [null, 'abc', '+1', "123\n456", str_repeat('1', 41)],
            'contact.governorate' => ['', str_repeat('أ', 121)], 'contact.city' => ['', str_repeat('أ', 121)],
            'contact.address' => ['', str_repeat('أ', 501)], 'contact.notes' => [str_repeat('أ', 1001), '<b>x</b>', "x\0y"],
            'contact.email' => ['forged@example.test'], 'email' => ['forged@example.test'],
            'price' => ['1'], 'total' => ['1'], 'status' => ['confirmed'], 'payment_required_now' => [true], 'customer_id' => ['forged']] as $path => $values) {
            foreach ($values as $index => $value) {
                $cases[$path.'-'.$index] = [$path, $value];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidFields')]
    public function test_strict_input_rejects_invalid_and_forged_fields_without_writes(string $path, mixed $value): void
    {
        $this->seedCart();
        $p = $this->payload();
        data_set($p, $path, $value);
        $this->postJson(self::URL, $p)->assertUnprocessable();
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertDatabaseCount('checkout_intent_items', 0);
    }

    public function test_empty_stale_hidden_zero_missing_price_and_unavailable_block_atomically(): void
    {
        $p = $this->payload();
        $p['expected_cart_version'] = '0';
        $this->postJson(self::URL, $p)->assertConflict();
        $product = $this->product(['price_usd' => '2']);
        $this->addItem($product);
        $this->postJson(self::URL, $p)->assertConflict();
        $p['expected_cart_version'] = '1';
        foreach ([['status' => 'hidden'], ['status' => 'active', 'price_usd' => '0'], ['price_usd' => null], ['price_usd' => '2', 'availability_status' => 'unavailable']] as $values) {
            $product->update($values);
            $this->postJson(self::URL, $p)->assertConflict();
            $this->assertDatabaseCount('checkout_intents', 0);
            $this->assertDatabaseCount('checkout_intent_items', 0);
        }
    }

    public function test_on_request_is_eligible_with_review_and_selected_currency_price(): void
    {
        $product = $this->product(['price_usd' => '2', 'availability_status' => 'on_request']);
        $this->addItem($product, null, 2147483647);
        $p = $this->payload();
        $p['currency'] = 'SYP';
        $this->postJson(self::URL, $p)->assertConflict();
        $p['currency'] = 'USD';
        $r = $this->postJson(self::URL, $p)->assertCreated();
        $this->read(self::URL.'/'.$r->json('data.id'))->assertOk()->assertJsonPath('data.items.0.quantity', 2147483647)
            ->assertJsonPath('data.items.0.requires_fulfillment_review_at_submission', true);
    }

    public function test_ownership_list_privacy_and_unsupported_mutations(): void
    {
        $this->seedCart();
        $id = $this->postJson(self::URL, $this->payload())->assertCreated()->json('data.id');
        $list = $this->read(self::URL)->assertOk()->assertJsonCount(1, 'data');
        foreach (['contact', 'email', 'items', 'source_cart_version'] as $key) {
            $this->assertArrayNotHasKey($key, $list->json('data.0'));
        }
        $this->read(self::URL.'?per_page=49')->assertUnprocessable();
        $this->read(self::URL.'?status=submitted')->assertUnprocessable();
        $this->putJson(self::URL.'/'.$id, ['status' => 'confirmed'])->assertStatus(405);
        $this->deleteJson(self::URL.'/'.$id)->assertStatus(405);
        $this->actingAs($this->otherCustomer(), 'customer');
        $foreign = $this->read(self::URL.'/'.$id)->assertNotFound()->json('error.code');
        $this->assertSame($foreign, $this->read(self::URL.'/'.Str::uuid7())->assertNotFound()->json('error.code'));
        $this->read(self::URL)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_invalid_authoritative_email_blocks_without_profile_mutation(): void
    {
        $this->seedCart();
        $this->owner->update(['email' => 'invalid']);
        $this->postJson(self::URL, $this->payload())->assertConflict();
        $this->assertSame('invalid', $this->owner->fresh()->email);
        $this->assertDatabaseCount('checkout_intents', 0);
    }

    public function test_real_database_failure_after_header_rolls_back_every_row(): void
    {
        $this->seedCart();
        $delegate = app(CartSnapshot::class);
        $mock = \Mockery::mock(CartSnapshot::class);
        $mock->shouldReceive('lockedForSubmission')->once()->andReturnUsing(function (...$args) use ($delegate) {
            $source = $delegate->lockedForSubmission(...$args);
            $source['items'][0]['quantity'] = 0;

            return $source;
        });
        $this->app->instance(CartSnapshot::class, $mock);
        Log::shouldReceive('withContext', 'withoutContext')->andReturnSelf();
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) {
            $this->assertSame('preliminary_checkout_failed', $message);
            $this->assertSame(['exception_type', 'request_id'], array_keys($context));
            $this->assertSame(QueryException::class, $context['exception_type']);
            $this->assertNotEmpty($context['request_id']);

            return true;
        });
        $this->postJson(self::URL, $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertDatabaseCount('checkout_intent_items', 0);
        $this->assertSame('1', Cart::query()->sole()->version);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_schema_rejects_null_selection_duplicates_bad_quantities_and_restricts_deletion(): void
    {
        $this->seedCart();
        $this->postJson(self::URL, $this->payload())->assertCreated();
        $row = (array) DB::table('checkout_intent_items')->sole();
        unset($row['selection_key']);
        $row['id'] = (string) Str::uuid7();
        $row['line_order'] = 2;
        $row['source_cart_item_id'] = (string) Str::uuid7();
        $this->assertConstraint(fn () => DB::table('checkout_intent_items')->insert($row), 1062);
        $this->assertConstraint(fn () => DB::table('checkout_intent_items')->update(['quantity' => 0]), 3819);
        $this->assertConstraint(fn () => DB::table('products')->delete(), 1451);
        $this->assertConstraint(fn () => CheckoutIntent::query()->delete(), 1451);
    }

    public function test_post_quota_counts_replays_and_does_not_change_evaluation_quota(): void
    {
        $this->seedCart();
        $p = $this->payload();
        $this->postJson(self::URL, $p)->assertCreated();
        for ($i = 1; $i < 10; $i++) {
            $this->postJson(self::URL, $p)->assertOk();
        }
        $this->postJson(self::URL, $p)->assertStatus(429);
        $this->postJson('/api/v1/checkout/evaluations', ['currency' => 'USD', 'expected_cart_version' => '1'])->assertOk();
        $this->assertDatabaseCount('checkout_intents', 1);
    }

    public function test_csrf_guest_bearer_suspension_and_request_id(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson(self::URL, $this->payload())->assertUnauthorized();
        $this->postJson(self::URL, $this->payload(), ['Authorization' => 'Bearer forged'])->assertUnauthorized();
        $this->actingAs($this->owner, 'customer');
        $this->seedCart();
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->postJson(self::URL, $this->payload())->assertStatus(419);
        $r = $this->postJson(self::URL, $this->payload(), ['X-CSRF-TOKEN' => $this->app['session']->token()])->assertCreated();
        $this->assertNotEmpty($r->headers->get('X-Request-ID'));
        $this->owner->update(['account_status' => 'suspended']);
        $this->postJson(self::URL, $this->payload(), ['X-CSRF-TOKEN' => $this->app['session']->token()])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner, 'customer');
        $this->read(self::URL)->assertForbidden();
    }

    public static function forgedCommercialFields(): array
    {
        return array_map(fn ($field) => [$field], ['cart_id', 'product_id', 'variant_id', 'quantity', 'unit_price', 'subtotal', 'deposit', 'required_payment', 'payment_method', 'verified', 'is_paid', 'reservation', 'stock', 'role', 'staff_id', 'items']);
    }

    #[DataProvider('forgedCommercialFields')]
    public function test_all_other_commercial_and_selection_fields_rejected(string $field): void
    {
        $p = $this->payload();
        $p[$field] = 'forged';
        $this->postJson(self::URL, $p)->assertUnprocessable();
        $this->assertDatabaseCount('checkout_intents', 0);
    }

    public function test_variant_prices_visibility_and_composite_fk_ownership(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, ['price_usd' => '1']);
        $this->addItem($product, $variant->id);
        $variant->update(['price_usd' => null]);
        $p = $this->payload();
        $this->postJson(self::URL, $p)->assertConflict();
        $variant->update(['price_usd' => '1']);
        $r = $this->postJson(self::URL, $p)->assertCreated();
        $this->read(self::URL.'/'.$r->json('data.id'))->assertOk()->assertJsonPath('data.items.0.variant_id', $variant->id);
        $other = $this->product();
        $otherVariant = $this->variant($other);
        $this->assertConstraint(fn () => DB::table('checkout_intent_items')->update(['variant_id' => $otherVariant->id]), 1452);
        $product->brand->update(['status' => 'hidden']);
        $p['operation_id'] = (string) Str::uuid7();
        $this->postJson(self::URL, $p)->assertConflict();
    }

    public function test_query_forgery_body_shape_and_large_body_rejected(): void
    {
        $this->postJson(self::URL.'?currency=USD', $this->payload())->assertUnprocessable();
        $this->postJson(self::URL, [])->assertUnprocessable();
        $p = $this->payload();
        $p['contact'] = [];
        $this->postJson(self::URL, $p)->assertUnprocessable();
        $p['contact'] = ['notes' => str_repeat('x', 17000)];
        $this->postJson(self::URL, $p)->assertStatus(413);
        $this->getJson(self::URL, ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('checkout_intents', 0);
    }

    public function test_full_cart_bulk_insert_bounded_queries_and_over_cap_no_partial_request(): void
    {
        config(['customer_collections.mutations_per_minute' => 1000]);
        $counts = [];
        $detailCounts = [];
        for ($i = 1; $i <= 201; $i++) {
            $product = $this->product(['price_usd' => '1']);
            if ($i <= 200) {
                $this->addItem($product);
            } else {
                Cart::query()->sole()->items()->create(['product_id' => $product->id, 'quantity' => 1]);
            }
            if (in_array($i, [1, 2, 50, 200], true)) {
                $p = $this->payload();
                $p['expected_cart_version'] = (string) $i;
                DB::enableQueryLog();
                DB::flushQueryLog();
                $r = $this->postJson(self::URL, $p)->assertCreated()->assertJsonPath('data.item_count', $i);
                $queries = DB::getQueryLog();
                $counts[$i] = count($queries);
                $this->assertCount(1, array_filter($queries, fn ($q) => str_starts_with($q['query'], 'insert into `checkout_intent_items`')));
                DB::flushQueryLog();
                $this->read(self::URL.'/'.$r->json('data.id'))->assertOk()->assertJsonCount($i, 'data.items');
                $detailQueries = DB::getQueryLog();
                $detailCounts[$i] = count($detailQueries);
                $this->assertLessThanOrEqual(5, count($detailQueries));
                $this->assertCount(0, array_filter($detailQueries, fn ($q) => str_contains($q['query'], 'from `products`')));
                DB::disableQueryLog();
            }
        }
        $this->assertSame($counts[1], $counts[200]);
        $this->assertSame($counts[2], $counts[50]);
        $p = $this->payload();
        $p['expected_cart_version'] = '200';
        $this->postJson(self::URL, $p)->assertConflict();
        $this->assertDatabaseCount('checkout_intents', 4);
        $this->assertDatabaseCount('checkout_intent_items', 253);
        $plan = DB::select('EXPLAIN FORMAT=TRADITIONAL SELECT id FROM checkout_intents WHERE customer_id = ? ORDER BY created_at DESC, id ASC LIMIT 12', [$this->owner->id]);
        $this->assertNotNull($plan[0]->key);
        fwrite(STDOUT, 'PRELIMINARY_QUERY_COUNTS '.json_encode($counts).' DETAIL_QUERY_COUNTS '.json_encode($detailCounts).' HISTORY_EXPLAIN '.json_encode($plan).PHP_EOL);
    }

    public function test_scoped_operation_ids_stable_pagination_and_safe_history_queries(): void
    {
        $this->seedCart();
        $p = $this->payload();
        $one = $this->postJson(self::URL, $p)->assertCreated()->json('data.id');
        $p2 = $p;
        $p2['operation_id'] = (string) Str::uuid7();
        $two = $this->postJson(self::URL, $p2)->assertCreated()->json('data.id');
        DB::table('checkout_intents')->update(['created_at' => '2026-10-13 00:00:00']);
        $ids = [$one, $two];
        sort($ids);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->read(self::URL.'?per_page=1')->assertOk()->assertJsonPath('data.0.id', $ids[0])->assertJsonPath('meta.total', 2);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        fwrite(STDOUT, 'PRELIMINARY_LIST_QUERY_COUNT '.count($queries).PHP_EOL);
        $this->assertLessThanOrEqual(5, count($queries));
        $this->read(self::URL.'?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $ids[1]);
        $other = $this->otherCustomer();
        $this->actingAs($other, 'customer');
        app(CartOperations::class)->add($other->id, $this->product(['price_usd' => '2'])->id, null, 1);
        $this->postJson(self::URL, $p)->assertCreated();
        $this->assertDatabaseCount('checkout_intents', 3);
    }

    public function test_tables_have_no_financial_fields_and_status_is_submitted_only(): void
    {
        $this->seedCart();
        $this->postJson(self::URL, $this->payload())->assertCreated();
        foreach (['checkout_intents', 'checkout_intent_items'] as $table) {
            $columns = Schema::getColumnListing($table);
            foreach (['price', 'unit_price', 'total', 'subtotal', 'payment_status', 'paid', 'stock', 'reserved_quantity'] as $name) {
                $this->assertNotContains($name, $columns);
            }
        }
        $this->assertConstraint(fn () => DB::table('checkout_intents')->update(['status' => 'confirmed']), 1265);
        $this->assertConstraint(fn () => DB::table('checkout_intent_items')->update(['requires_fulfillment_review' => 2]), 3819);
        $routes = collect(app('router')->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/checkout/intents'));
        $this->assertCount(3, $routes);
        foreach ($routes as $route) {
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains(EnsureActiveCustomer::class, $route->gatherMiddleware());
        }
    }

    public function test_actual_google_browser_session_submits_current_customer_email(): void
    {
        $this->seedCart();
        $this->app->instance(GoogleOAuthClient::class,
            new FakeGoogleOAuthClient($this->identity()));
        $this->app['auth']->forgetGuards();
        $this->continueBrowserSession($this->get('/api/v1/auth/google/callback'));
        $id = $this->postJson(self::URL, $this->payload())->assertCreated()->json('data.id');
        $this->read(self::URL.'/'.$id)->assertOk()->assertJsonPath('data.contact.email', 'customer@example.test');
    }

    public function test_ip_quota_blocks_other_customers_and_operates_without_redis(): void
    {
        $this->assertSame('array', config('cache.default'));
        config(['database.redis.default.host' => '192.0.2.1', 'database.redis.default.port' => 1]);
        $this->seedCart();
        $request = Request::create(self::URL, 'POST');
        $request->setUserResolver(fn () => $this->owner);
        $limits = (RateLimiter::limiter('preliminary-checkout'))($request);
        $this->assertSame(100, $limits[1]->maxAttempts);
        $key = md5('preliminary-checkout'.$limits[1]->key);
        for ($i = 0; $i < 100; $i++) {
            RateLimiter::hit($key, 3600);
        }
        $this->postJson(self::URL, $this->payload())->assertStatus(429)->assertHeader('Retry-After');
        RateLimiter::clear($key);
        $this->postJson(self::URL, $this->payload())->assertCreated();
    }

    public function test_isolation_guard_rejects_development_target_before_destructive_test_setup(): void
    {
        config(['database.connections.mysql.database' => 'sumaya_pharma']);
        try {
            $this->refreshTestDatabase();
            $this->fail('Unsafe database target must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('isolated sumaya_pharma_test', $e->getMessage());
        } finally {
            config(['database.connections.mysql.database' => 'sumaya_pharma_test']);
        }
    }
}
