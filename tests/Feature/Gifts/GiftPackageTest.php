<?php

namespace Tests\Feature\Gifts;

use App\Domains\Gifts\Application\ManageGiftPackage;
use App\Domains\Gifts\Infrastructure\GiftStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\GiftTestCase;

class GiftPackageTest extends GiftTestCase
{
    public function test_real_owner_forms_and_publication_lifecycle_preserve_snapshots_and_private_images(): void
    {
        $this->ownerLogin();
        $this->get(route('owner.gifts.index'))->assertOk()->assertSee('لا توجد أسعار');
        $this->get(route('owner.gifts.create'))->assertOk()->assertSee('إضافة عنصر يدوي');
        $id = $this->gift(publish: false);
        $slug = DB::table('gift_packages')->where('id', $id)->value('slug');
        $url = route('owner.gifts.mutate', ['id' => $id]);
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertNotFound();
        $this->post($url, $this->giftCommand('1', 'media', ['alt_text' => 'رسم آمن']) + ['image' => UploadedFile::fake()->image('gift.png')])->assertRedirect();
        $draft = GiftStore::revision($id, '2');
        $media = $draft['images'][0]['media_id'];
        $this->get('/api/v1/gifts/media/'.$media.'.png')->assertNotFound();
        $this->get(route('owner.gifts.media', ['id' => $media]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->post($url, $this->giftCommand('2', 'publish'))->assertRedirect();
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertOk()->assertJsonPath('data.published_version', '3')->assertJsonPath('data.prices.USD', '50.00');
        $this->get('/api/v1/gifts/media/'.$media.'.png')->assertOk();
        $this->post($url, $this->giftCommand('3', extra: ['values' => ['name' => 'مسودة جديدة']]))->assertRedirect();
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertJsonPath('data.name', 'باقة العناية');
        $this->get(route('owner.gifts.edit', ['id' => $id, 'revision' => '3']))->assertOk()->assertSee('لقطة تاريخية');
        $this->post($url, $this->giftCommand('4', 'publish'))->assertRedirect();
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertJsonPath('data.name', 'مسودة جديدة');
        $this->post($url, $this->giftCommand('5', 'unpublish'))->assertRedirect();
        $this->get('/api/v1/gifts/media/'.$media.'.png')->assertNotFound();
        $this->post($url, $this->giftCommand('6', 'archive'))->assertRedirect();
        $this->post($url, $this->giftCommand('7', 'restore'))->assertRedirect();
        $this->assertDatabaseHas('gift_packages', ['id' => $id, 'published_version' => null, 'archived_at' => null, 'lock_version' => 8]);
        $this->assertDatabaseCount('gift_package_revisions', 8);
        $this->assertDatabaseCount('gift_change_events', 8);
    }

    public function test_hybrid_composition_requires_exact_variant_but_not_component_prices(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        $id = $this->gift(['components' => [['type' => 'catalog', 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'sort_order' => 1], ['type' => 'manual', 'name' => 'بطاقة', 'quantity' => 1, 'sort_order' => 0]]]);
        $slug = DB::table('gift_packages')->where('id', $id)->value('slug');
        $response = $this->getJson('/api/v1/gifts/packages/'.$slug)->assertOk()->assertJsonPath('data.prices.USD', '50.00')->assertJsonCount(2, 'data.components');
        $this->assertSame('بطاقة', $response->json('data.components.0.name'));
        $variant->update(['price_usd' => '999.00']);
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertJsonPath('data.prices.USD', '50.00');
        $product->update(['status' => 'hidden']);
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertNotFound();
        $this->getJson('/api/v1/gifts/packages')->assertJsonCount(0, 'data');
        $product->update(['status' => 'active']);
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertOk();
    }

    public function test_operation_replay_stale_versions_and_permanent_slug_are_authoritative(): void
    {
        $this->ownerLogin();
        $this->view('owner.gifts.validation', ['validationErrors' => ['gift' => ['تعارض آمن']]])->assertSee('تعارض آمن');
        $command = $this->giftCommand(extra: ['slug' => 'permanent-gift', 'values' => ['name' => 'اسم']]);
        $url = route('owner.gifts.mutate');
        $this->post($url, $command)->assertRedirect();
        $this->post($url, $command)->assertRedirect();
        $this->post($url, array_replace($command, ['slug' => 'different']))->assertStatus(409);
        $id = DB::table('gift_packages')->value('id');
        $this->post(route('owner.gifts.mutate', ['id' => $id]), $this->giftCommand('0', extra: ['values' => ['name' => 'قديم']]))->assertStatus(409);
        $this->post(route('owner.gifts.mutate', ['id' => $id]), $this->giftCommand('1', extra: ['slug' => 'renamed']))->assertStatus(409);
        $this->post($url, $this->giftCommand(extra: ['slug' => 'permanent-gift', 'values' => ['name' => 'متكرر']]))->assertStatus(409);
        $this->assertDatabaseCount('gift_package_revisions', 1);
        $this->assertDatabaseCount('gift_change_events', 1);
        $this->post(route('owner.gifts.mutate', ['id' => (string) Str::uuid7()]), $this->giftCommand())->assertNotFound();
    }

    public function test_bounded_gallery_primary_fallback_and_draft_removal_preserve_public_bytes(): void
    {
        $id = $this->gift();
        $this->ownerLogin();
        $url = route('owner.gifts.mutate', ['id' => $id]);
        $old = GiftStore::revision($id, '3');
        $oldMedia = $old['images'][0]['media_id'];
        $this->post($url, $this->giftCommand('3', 'media', ['alt_text' => 'بديل']) + ['image' => UploadedFile::fake()->image('new.png')])->assertRedirect();
        $new = GiftStore::revision($id, '4');
        $newMedia = $new['images'][1]['media_id'];
        $this->get('/api/v1/gifts/media/'.$newMedia.'.png')->assertNotFound();
        $this->get('/api/v1/gifts/media/'.$oldMedia.'.png')->assertOk();
        $images = [['media_id' => $newMedia, 'alt_text' => 'بديل منشور', 'sort_order' => 0, 'is_primary' => true]];
        app(ManageGiftPackage::class)->execute($id, $this->owner, $this->giftCommand('4', extra: ['images' => $images]));
        $this->get('/api/v1/gifts/media/'.$oldMedia.'.png')->assertOk();
        $this->post($url, $this->giftCommand('5', 'publish'))->assertRedirect();
        $this->get('/api/v1/gifts/media/'.$oldMedia.'.png')->assertNotFound();
        $this->get('/api/v1/gifts/media/'.$newMedia.'.png')->assertOk();
        $this->get(route('owner.gifts.media', ['id' => $oldMedia]))->assertOk();
        $this->assertFileExists(Storage::disk('local')->path('gifts/'.$oldMedia.'.png'));
        $this->post($url, $this->giftCommand('6', extra: ['images' => [['media_id' => (string) Str::uuid7(), 'alt_text' => 'مرجع مزور', 'sort_order' => '0']]]))->assertStatus(422);
    }

    public function test_fifty_components_and_ten_images_are_supported_with_real_caps(): void
    {
        $components = [];
        for ($i = 0; $i < 50; $i++) {
            $components[] = ['type' => 'manual', 'name' => 'عنصر '.$i, 'quantity' => 100, 'sort_order' => $i];
        }
        $id = $this->gift(['components' => $components], false);
        for ($i = 1; $i <= 10; $i++) {
            app(ManageGiftPackage::class)->execute($id, $this->owner, $this->giftCommand((string) $i, 'media', ['alt_text' => 'صورة '.$i]), UploadedFile::fake()->image('safe.png', 5, 5));
        }
        $this->ownerLogin();
        $this->post(route('owner.gifts.mutate', ['id' => $id]), $this->giftCommand('11', 'media', ['alt_text' => 'زائدة']) + ['image' => UploadedFile::fake()->image('extra.png')])->assertStatus(422);
        $this->assertCount(10, Storage::disk('local')->allFiles('gifts'));
        $this->post(route('owner.gifts.mutate', ['id' => $id]), $this->giftCommand('11', 'publish'))->assertRedirect();
        $r = GiftStore::revision($id, '12');
        $this->assertCount(50, $r['components']);
        $this->assertCount(10, $r['images']);
        $this->assertSame(100, $r['components'][49]['quantity']);
        $this->assertDatabaseCount('gift_package_revisions', 12);
    }
}
