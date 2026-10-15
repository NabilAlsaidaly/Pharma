<?php

namespace Tests\Feature\CatalogAdministration;

use App\Domains\Catalog\Application\Administration\ArchiveCatalogRecord;
use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Catalog\Application\Administration\ManageCatalogMedia;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\OwnerDashboardTestCase;

class CatalogMediaTest extends OwnerDashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_real_upload_is_reencoded_generated_safe_primary_atomic_archivable_and_restorable_as_secondary(): void
    {
        $product = $this->product();
        $this->ownerLogin();
        $url = route('owner.catalog.upload', ['type' => 'products', 'id' => $product->id]);
        $this->post($url, ['lock_version' => '1', 'image' => UploadedFile::fake()->image('صورة.jpg', 80, 60)])->assertRedirect();
        $first = ProductImage::query()->firstOrFail();
        $this->assertMatchesRegularExpression('#^catalog/products/[0-9a-f-]+\.png$#', $first->path);
        Storage::disk('public')->assertExists($first->path);
        $this->assertSame(IMAGETYPE_PNG, getimagesize(Storage::disk('public')->path($first->path))[2]);
        $this->post($url, ['lock_version' => '2', 'image' => UploadedFile::fake()->image('other.png')])->assertRedirect();
        $second = ProductImage::query()->where('id', '!=', $first->id)->firstOrFail();
        foreach ([$first, $second] as $image) {
            $this->patch(route('owner.catalog.image', ['id' => $product->id, 'image' => $image->id]), ['lock_version' => '1', 'sort_order' => 3, 'is_primary' => 1])->assertRedirect();
        }
        $this->assertSame(1, ProductImage::query()->where('is_primary', 1)->count());
        $this->assertSame($second->id, $product->fresh()->primaryImage->id);
        $archive = app(ArchiveCatalogRecord::class);
        $change = new CatalogChange($this->owner, 'media-fixture');
        $archive->execute('images', $second->id, '2', false, $change);
        $this->assertSame($first->id, $product->fresh()->primaryImage->id);
        Storage::disk('public')->assertExists($second->path);
        $archive->execute('images', $second->id, '3', true, $change);
        $this->assertFalse($second->fresh()->is_primary);
        $this->assertSame($first->id, $product->fresh()->primaryImage->id);
        $this->assertSame(2, ProductImage::query()->count());
        $this->assertDatabaseHas('catalog_audit_events', ['entity_id' => $second->id, 'action' => 'restore']);
    }

    public static function invalidMedia(): array
    {
        return ['svg' => ['bad.svg', '<svg onload="alert(1)"></svg>', 'image/svg+xml'], 'php' => ['bad.php', '<?php die();', 'image/png'], 'double' => ['bad.php.png', 'RASTER', 'image/png'], 'traversal' => ['../bad.png', 'RASTER', 'image/png'], 'corrupt' => ['bad.png', 'not an image', 'image/png']];
    }

    #[DataProvider('invalidMedia')]
    public function test_untrusted_uploads_never_create_file_metadata_or_audit(string $name, string $bytes, string $mime): void
    {
        $product = $this->product();
        $this->ownerLogin();
        $file = $bytes === 'RASTER' ? UploadedFile::fake()->image($name) : UploadedFile::fake()->createWithContent($name, $bytes);
        $this->post(route('owner.catalog.upload', ['type' => 'products', 'id' => $product->id]), ['lock_version' => '1', 'image' => $file])->assertStatus(422);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseCount('catalog_audit_events', 0);
    }

    public function test_size_dimensions_and_count_limits_and_cross_product_image_edits_are_rejected(): void
    {
        $product = $this->product();
        $this->ownerLogin();
        $url = route('owner.catalog.upload', ['type' => 'products', 'id' => $product->id]);
        $this->post($url, ['lock_version' => '1', 'image' => UploadedFile::fake()->image('large.png')->size(5121)])->assertStatus(422);
        $this->post($url, ['lock_version' => '1', 'image' => UploadedFile::fake()->image('wide.png', 4097, 1)])->assertStatus(422);
        for ($i = 0; $i < 20; $i++) {
            ProductImage::query()->create(['product_id' => $product->id, 'path' => 'retained/'.$i.'.png']);
        }
        $this->post($url, ['lock_version' => '1', 'image' => UploadedFile::fake()->image('extra.png')])->assertStatus(422);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $other = $this->product();
        $image = ProductImage::query()->firstOrFail();
        $this->patch(route('owner.catalog.image', ['id' => $other->id, 'image' => $image->id]), ['lock_version' => '1', 'sort_order' => 0, 'is_primary' => 1])->assertNotFound();
        $this->assertDatabaseCount('catalog_audit_events', 0);
        $archived = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'catalog/products/retained.png']);
        Storage::disk('public')->put($archived->path, 'retained fixture bytes');
        $archived->delete();
        try {
            app(ArchiveCatalogRecord::class)->execute('images', $archived->id, '1', true, new CatalogChange($this->owner, 'cap-fixture'));
            $this->fail('Restoration must respect the same current-image cap as uploads.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(20, ProductImage::query()->where('product_id', $product->id)->count());
        $this->assertTrue($archived->fresh()->trashed());
        Storage::disk('public')->assertExists($archived->path);
        $this->assertDatabaseCount('catalog_audit_events', 0);
    }

    public function test_stale_upload_compensates_only_new_file_old_brand_logo_survives_and_failed_audit_rolls_back(): void
    {
        $brand = $this->brand();
        $this->ownerLogin();
        $url = route('owner.catalog.upload', ['type' => 'brands', 'id' => $brand->id]);
        $this->post($url, ['lock_version' => '1', 'image' => UploadedFile::fake()->image('logo.png')])->assertRedirect();
        $old = $brand->fresh()->logo_path;
        $this->post($url, ['lock_version' => '1', 'image' => UploadedFile::fake()->image('stale.png')])->assertStatus(409);
        $this->assertSame([$old], Storage::disk('public')->allFiles());
        $this->post($url, ['lock_version' => '2', 'image' => UploadedFile::fake()->image('replacement.png')])->assertRedirect();
        Storage::disk('public')->assertExists($old);
        $new = $brand->fresh()->logo_path;
        $this->assertNotSame($old, $new);
        $this->assertCount(2, Storage::disk('public')->allFiles());
        $product = $this->product();
        // Real MySQL insert failure after the file write: wrong audit FK, no partial image row/file.
        $fakeOwner = clone $this->owner;
        $fakeOwner->id = '019aa000-0000-7000-8000-000000000001';
        try {
            app(ManageCatalogMedia::class)->upload('products', $product->id, '1', UploadedFile::fake()->image('rollback.png'), new CatalogChange($fakeOwner, 'rollback-fixture'));
            $this->fail('Audit FK must fail.');
        } catch (QueryException $exception) {
            $this->assertDatabaseCount('product_images', 0);
            $this->assertCount(2, Storage::disk('public')->allFiles());
            $this->assertSame(2, DB::table('catalog_audit_events')->count());
        }
    }

    public function test_missing_archived_file_refuses_restore_and_primary_unique_index_remains_generated(): void
    {
        $product = $this->product();
        $image = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'missing.png', 'is_primary' => true]);
        $archive = app(ArchiveCatalogRecord::class);
        $change = new CatalogChange($this->owner, 'fixture');
        $archive->execute('images', $image->id, '1', false, $change);
        try {
            $archive->execute('images', $image->id, '2', true, $change);
            $this->fail('Missing file must not restore.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('catalog_audit_events')->count());
        $this->assertSame(0, ProductImage::query()->count());
        $column = DB::selectOne("SELECT EXTRA FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='product_images' AND COLUMN_NAME='primary_product_id'");
        $this->assertStringContainsString('STORED GENERATED', $column->EXTRA);
    }

    public function test_mismatched_extension_and_executable_polyglot_tail_are_rejected_before_storage(): void
    {
        $product = $this->product();
        $this->ownerLogin();
        $png = UploadedFile::fake()->image('actual.png', 8, 8);
        $bytes = file_get_contents($png->getPathname());
        foreach ([UploadedFile::fake()->createWithContent('wrong.jpg', $bytes), UploadedFile::fake()->createWithContent('tail.png', $bytes.'<?php die();')] as $file) {
            $this->post(route('owner.catalog.upload', ['type' => 'products', 'id' => $product->id]), ['lock_version' => '1', 'image' => $file])->assertStatus(422);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseCount('catalog_audit_events', 0);
    }
}
