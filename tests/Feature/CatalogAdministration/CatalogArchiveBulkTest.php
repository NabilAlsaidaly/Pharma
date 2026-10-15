<?php

namespace Tests\Feature\CatalogAdministration;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Catalog\Application\Administration\ArchiveCatalogRecord;
use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Catalog\Application\Administration\WriteCatalogRecord;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Favorites\Application\FavoriteOperations;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\OwnerDashboardTestCase;

class CatalogArchiveBulkTest extends OwnerDashboardTestCase
{
    private function preview(string $type, array $payload)
    {
        return $this->post(route('owner.catalog.preview', ['type' => $type]), $payload);
    }

    private function confirm(): array
    {
        return ['token' => session('catalog_confirmation.token'), 'confirm' => '1'];
    }

    public function test_archive_preserves_favorites_cart_and_history_then_restore_keeps_identity_status_and_snapshot(): void
    {
        $customer = $this->customer();
        $product = $this->product(['price_usd' => '12']);
        app(FavoriteOperations::class)->add($customer->id, $product->id);
        app(CartOperations::class)->add($customer->id, $product->id, null, 2);
        $payload = ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => ['full_name' => 'Customer', 'phone' => '+963999999999', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'عنوان الاختبار', 'notes' => null]];
        $intent = app(SubmitPreliminaryCheckoutRequest::class)->execute($customer->id, $payload);
        $facts = [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()];
        $this->ownerLogin();
        $this->preview('products', ['scope' => 'ids', 'ids' => [$product->id], 'action' => 'archive'])->assertOk()->assertSee('1 سجل');
        $this->assertFalse($product->fresh()->trashed());
        $this->post(route('owner.catalog.execute'), $this->confirm())->assertRedirect();
        $this->assertNull(Product::query()->find($product->id));
        $this->assertDatabaseCount('favorites', 1);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(0, app(FavoriteOperations::class)->listing($customer->id, 12)->total());
        $this->get('/api/v1/products/'.$product->slug)->assertNotFound();
        $this->get(route('owner.products.index'))->assertOk()->assertDontSee($product->name);
        $this->get(route('owner.search', ['q' => $product->name]))->assertOk()->assertDontSee(route('owner.products.show', ['id' => $product->id]), false);
        $this->get(route('owner.catalog.recycle', ['type' => 'products']))->assertOk()->assertSee($product->id);
        $metrics = $this->get(route('owner.dashboard'))->assertOk()->viewData('metrics');
        $this->assertSame(0, $metrics['products']);
        $this->assertSame(1, $metrics['archived_products']);
        $this->assertSame(1, $metrics['requests']);
        try {
            app(CartOperations::class)->add($customer->id, $product->id, null, 1);
            $this->fail('Archived admission must fail.');
        } catch (CartConflict|HttpException $e) {
            $this->assertDatabaseCount('cart_items', 1);
        }
        $item = DB::table('cart_items')->first();
        app(CartOperations::class)->update($customer->id, $item->id, ['quantity' => 1, 'expected_version' => '1']);
        $this->assertSame(1, DB::table('cart_items')->first()->quantity);
        app(CartOperations::class)->remove($customer->id, $item->id);
        $this->assertDatabaseCount('cart_items', 0);
        $this->preview('products', ['scope' => 'matching', 'action' => 'restore', 'filters' => ['archived' => 1]])->assertOk();
        $this->post(route('owner.catalog.execute'), $this->confirm())->assertRedirect();
        $this->assertSame($product->id, $product->fresh()->id);
        $this->assertSame('active', $product->fresh()->status->value);
        $this->assertSame(1, app(FavoriteOperations::class)->listing($customer->id, 12)->total());
        $this->assertSame($facts, [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()]);
        $this->assertSame($intent['intent']->id, app(SubmitPreliminaryCheckoutRequest::class)->execute($customer->id, $payload)['intent']->id);
    }

    public function test_matching_selection_across_pages_is_exact_atomic_audited_and_replay_is_rejected(): void
    {
        $brand = $this->brand();
        for ($i = 0; $i < 28; $i++) {
            $this->product(['brand_id' => $brand->id, 'name' => 'مرطب '.$i]);
        }
        $unselected = $this->product(['name' => 'غير مطابق']);
        $this->ownerLogin();
        $this->get(route('owner.products.index', ['q' => 'مرطب', 'per_page' => 10]))->assertOk()->assertSee('كل النتائج المطابقة (28)');
        $this->preview('products', ['scope' => 'matching', 'action' => 'hidden', 'filters' => ['q' => 'مرطب']])->assertOk()->assertSee('28 سجل');
        $confirmation = $this->confirm();
        $this->post(route('owner.catalog.execute'), $confirmation)->assertRedirect();
        $this->assertSame(28, Product::query()->where('status', 'hidden')->count());
        $this->assertSame('active', $unselected->fresh()->status->value);
        $this->assertDatabaseCount('catalog_audit_events', 28);
        $this->assertSame(1, DB::table('catalog_audit_events')->distinct()->count('bulk_id'));
        $this->post(route('owner.catalog.execute'), $confirmation)->assertStatus(409);
        $this->assertDatabaseCount('catalog_audit_events', 28);
    }

    public function test_changed_filtered_set_or_stale_record_rejects_whole_batch_without_partial_audit(): void
    {
        $one = $this->product(['name' => 'مرطب أول']);
        $two = $this->product(['name' => 'مرطب ثان']);
        $this->ownerLogin();
        $this->preview('products', ['scope' => 'matching', 'action' => 'hidden', 'filters' => ['q' => 'مرطب']])->assertOk();
        $this->product(['name' => 'مرطب جديد']);
        $this->post(route('owner.catalog.execute'), $this->confirm())->assertStatus(409);
        $this->assertSame(0, Product::query()->where('status', 'hidden')->count());
        $this->assertDatabaseCount('catalog_audit_events', 0);
        $this->preview('products', ['scope' => 'ids', 'ids' => [$one->id, $two->id], 'action' => 'hidden'])->assertOk();
        app(WriteCatalogRecord::class)->execute('products', $two->id, ['lock_version' => '1', 'name' => 'تعديل متزامن'], new CatalogChange($this->owner, 'fixture'));
        $this->post(route('owner.catalog.execute'), $this->confirm())->assertStatus(409);
        $this->assertSame('active', $one->fresh()->status->value);
        $this->assertDatabaseCount('catalog_audit_events', 1);
    }

    public function test_oversize_confirmation_unknown_resource_action_and_invalid_ids_fail_safely(): void
    {
        $brand = $this->brand();
        for ($i = 0; $i < 101; $i++) {
            $this->product(['brand_id' => $brand->id]);
        }
        $this->ownerLogin();
        $this->preview('products', ['scope' => 'matching', 'action' => 'archive'])->assertStatus(422)->assertSee('مئة');
        $this->preview('products', ['scope' => 'ids', 'ids' => ['bad'], 'action' => 'archive'])->assertStatus(422);
        $this->preview('products', ['scope' => 'ids', 'ids' => [(string) Str::uuid7()], 'action' => 'archive'])->assertStatus(422);
        $this->preview('brands', ['scope' => 'matching', 'action' => 'recommend'])->assertStatus(422);
        $this->post('/fixture-dashboard-portal/catalog/customers/preview', ['scope' => 'matching', 'action' => 'archive'])->assertNotFound();
        $this->post(route('owner.catalog.execute'), ['token' => (string) Str::uuid7(), 'confirm' => 1])->assertStatus(409);
        $this->assertDatabaseCount('catalog_audit_events', 0);
        $this->assertSame(101, Product::query()->count());
    }

    public function test_confirmation_requires_explicit_acceptance_and_expiration_is_enforced(): void
    {
        $product = $this->product();
        $this->ownerLogin();
        $this->preview('products', ['scope' => 'ids', 'ids' => [$product->id], 'action' => 'archive'])->assertOk();
        $this->post(route('owner.catalog.execute'), ['token' => session('catalog_confirmation.token')])->assertStatus(422);
        $confirmation = $this->confirm();
        $this->travel(11)->minutes();
        $this->post(route('owner.catalog.execute'), $confirmation)->assertStatus(409);
        $this->assertDatabaseCount('catalog_audit_events', 0);
    }

    public function test_archive_restore_are_idempotent_with_current_version_and_parent_brand_remains_archived(): void
    {
        $product = $this->product();
        $change = new CatalogChange($this->owner, 'fixture');
        $archive = app(ArchiveCatalogRecord::class);
        $archive->execute('products', $product->id, '1', false, $change);
        $archive->execute('products', $product->id, '2', false, $change);
        $archive->execute('brands', $product->brand_id, '1', false, $change);
        $archive->execute('products', $product->id, '2', true, $change);
        $archive->execute('products', $product->id, '3', true, $change);
        $this->assertSame(3, DB::table('catalog_audit_events')->count());
        $this->get('/api/v1/products/'.$product->slug)->assertNotFound();
        $this->assertTrue($product->brand()->withTrashed()->firstOrFail()->trashed());
        $this->assertSame('active', $product->fresh()->status->value);
    }
}
