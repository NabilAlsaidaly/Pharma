<?php

namespace Tests\Feature\Gifts;

use App\Domains\Gifts\Application\ManageGiftPackage;
use App\Domains\Gifts\Infrastructure\GiftStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\GiftTestCase;

class GiftValidationTest extends GiftTestCase
{
    public static function malformed(): array
    {
        $manual = ['type' => 'manual', 'name' => 'صندوق', 'quantity' => 1, 'sort_order' => 0];

        return array_map(fn ($extra) => [$extra], [
            ['values' => ['price_usd' => '-1']], ['values' => ['price_syp' => '1.001']], ['values' => ['price_usd' => 1.5]], ['values' => ['price_usd' => '1e2']], ['values' => ['price_usd' => '10000000000000000.00']], ['values' => ['price_usd' => []]], ['values' => ['name' => '!!!']], ['values' => ['description' => '<script>bad</script>']], ['values' => ['description' => '&#x3c;script>']], ['values' => ['stock' => 100]], ['values' => ['availability' => 'sold']], ['values' => ['sort_order' => '-1']], ['values' => 'not an object'], ['images' => 'invalid'], ['components' => 'invalid'], ['occasion_ids' => 'invalid'], ['components' => [null]], ['components' => [array_replace($manual, ['quantity' => '1'])]], ['components' => [array_replace($manual, ['quantity' => 1.0])]], ['components' => [array_replace($manual, ['quantity' => true])]], ['components' => [array_replace($manual, ['quantity' => 0])]], ['components' => [array_replace($manual, ['quantity' => 101])]], ['components' => [array_replace($manual, ['name' => '<img src=x>'])]], ['components' => [$manual, $manual]], ['components' => [$manual, array_replace($manual, ['name' => ' صندوق '])]], ['components' => array_fill(0, 51, $manual)], ['components' => [['type' => 'catalog', 'quantity' => 1, 'sort_order' => 0]]], ['values' => ['APP_KEY' => 'forged']], ['quantity' => 2], ['slug' => '../escape'],
        ]);
    }

    #[DataProvider('malformed')]
    public function test_malformed_owner_inputs_are_rejected_without_partial_writes(array $extra): void
    {
        $this->ownerLogin();
        $input = $this->giftCommand(extra: array_replace(['slug' => 'valid-gift', 'values' => ['name' => 'باقة']], $extra));
        try {
            app(ManageGiftPackage::class)->execute(null, $this->owner, $input);
            $this->fail('Malformed gift command must fail.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('gift_packages', 0);
        $this->assertDatabaseCount('gift_change_events', 0);
    }

    public function test_decimal_boundaries_null_zero_and_publish_requirements(): void
    {
        $this->ownerLogin();
        $id = $this->gift(['values' => ['name' => 'حدود', 'price_usd' => null, 'price_syp' => '0.00']], false);
        $url = route('owner.gifts.mutate', ['id' => $id]);
        $this->post($url, $this->giftCommand('1', 'publish'))->assertStatus(422);
        app(ManageGiftPackage::class)->execute($id, $this->owner, $this->giftCommand('1', 'media', ['alt_text' => 'صورة']), UploadedFile::fake()->image('x.png'));
        $this->post($url, $this->giftCommand('2', 'publish'))->assertStatus(422);
        $this->post($url, $this->giftCommand('2', extra: ['values' => ['price_usd' => '9999999999999999.99', 'price_syp' => '0.01', 'availability' => 'available']]))->assertRedirect();
        $this->post($url, $this->giftCommand('3', 'publish'))->assertRedirect();
        $r = GiftStore::revision($id, '4');
        $this->assertSame('9999999999999999.99', $r['price_usd']);
        $this->assertSame('0.01', $r['price_syp']);
        DB::table('gift_packages')->where('id', $id)->update(['published_version' => null, 'lock_version' => '9007199254740993']);
        DB::table('gift_package_revisions')->where('id', $r['id'])->update(['version' => '9007199254740993']);
        $this->post($url, $this->giftCommand('9007199254740993', extra: ['values' => ['name' => 'إصدار كبير']]))->assertRedirect();
        $this->post($url, $this->giftCommand('9007199254740994', 'publish'))->assertRedirect();
        $slug = DB::table('gift_packages')->where('id', $id)->value('slug');
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertJsonPath('data.published_version', '9007199254740995');
    }

    public static function blockingCatalogChanges(): array
    {
        return [['product_hidden'], ['product_archive'], ['brand_hidden'], ['brand_archive'], ['variant_archive'], ['variant_unavailable'], ['product_unavailable']];
    }

    #[DataProvider('blockingCatalogChanges')]
    public function test_live_catalog_changes_block_package_detail_media_and_new_submission(string $change): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        $id = $this->gift(['components' => [['type' => 'catalog', 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'sort_order' => 0]]]);
        $r = GiftStore::revision($id, '3');
        $slug = DB::table('gift_packages')->where('id', $id)->value('slug');
        match ($change) {
            'product_hidden' => $product->update(['status' => 'hidden']), 'product_archive' => $product->delete(),
            'brand_hidden' => $product->brand->update(['status' => 'hidden']), 'brand_archive' => $product->brand->delete(),
            'variant_archive' => $variant->delete(), 'variant_unavailable' => $variant->update(['availability_status' => 'unavailable']),
            'product_unavailable' => $product->update(['availability_status' => 'unavailable']),
        };
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertNotFound()->assertDontSee($product->name);
        $this->get('/api/v1/gifts/media/'.$r['images'][0]['media_id'].'.png')->assertNotFound();
        $this->actingAs($this->customer(), 'customer');
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id))->assertStatus(409);
        $this->assertDatabaseCount('gift_requests', 0);
    }

    public function test_variant_ownership_physical_archival_and_duplicate_link_rejection(): void
    {
        $this->ownerLogin();
        $p = $this->product();
        $v = $this->variant($p);
        $other = $this->product();
        $url = route('owner.gifts.mutate');
        foreach ([['product_id' => $p->id], ['product_id' => $other->id, 'variant_id' => $v->id]] as $selection) {
            $c = ['type' => 'catalog', 'selection' => $selection['product_id'].'|'.($selection['variant_id'] ?? ''), 'quantity' => '1', 'sort_order' => '0'];
            $this->post($url, $this->giftCommand(extra: ['slug' => 'no-default', 'values' => ['name' => 'باقة'], 'components' => [$c]]))->assertStatus(409);
        }
        $v->delete();
        $this->post($url, $this->giftCommand(extra: ['slug' => 'no-default', 'values' => ['name' => 'باقة'], 'components' => [['selection' => $p->id.'|', 'type' => 'catalog', 'quantity' => '1', 'sort_order' => '0']]]))->assertStatus(409);
        $c = ['type' => 'catalog', 'selection' => $other->id.'|', 'quantity' => '1', 'sort_order' => '0'];
        $this->post($url, $this->giftCommand(extra: ['slug' => 'duplicate', 'values' => ['name' => 'باقة'], 'components' => [$c, $c]]))->assertStatus(422);
        $this->assertDatabaseCount('gift_packages', 0);
    }
}
