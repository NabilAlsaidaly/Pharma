<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Interfaces\Http\CatalogImageUrl;
use Illuminate\Support\Facades\Storage;
use Tests\CatalogTestCase;

class CatalogImageTest extends CatalogTestCase
{
    public function test_primary_flag_beats_sort_order_and_fallback_is_deterministic(): void
    {
        $p = $this->product();
        $first = $this->image($p, ['sort_order' => 0]);
        $primary = $this->image($p, ['sort_order' => 20, 'is_primary' => true]);
        $this->get('/api/v1/products')->assertOk()->assertJsonPath('data.0.primary_image.id', $primary->id);
        $primary->update(['is_primary' => false]);
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.primary_image.id', $first->id);
        $tie = $this->image($p, ['sort_order' => 0]);
        $this->get('/api/v1/products')->assertOk()->assertJsonPath('data.0.primary_image.id', $first->id);
    }

    public function test_safe_metadata_serializes_configured_public_url_without_raw_paths(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('catalog/example.png', 'test fixture bytes');
        $p = $this->product();
        $i = $this->image($p, ['path' => 'catalog/example.png']);
        $r = $this->get('/api/v1/products/'.$p->slug);
        $r->assertOk()->assertJsonPath('data.images.0.url', Storage::disk('public')->url('catalog/example.png'));
        $this->assertArrayNotHasKey('path', $r->json('data.images.0'));
        $this->assertArrayNotHasKey('primary_product_id', $r->json('data.images.0'));
    }

    public function test_unsafe_absolute_traversal_encoded_and_executable_references_never_expose_a_url(): void
    {
        foreach (['/etc/private.png', 'C:\\private\\image.png', '../private.png', 'catalog/../x.png', 'https://evil.test/x.png', 'catalog/%2e%2e/x.png', 'catalog/x.php', 'catalog/x.svg', 'catalog/x.php.png', 'catalog/x.php.png?execute=1', "catalog/x\n.png"] as $path) {
            $this->assertNull(CatalogImageUrl::forPath($path));
        }
        $p = $this->product();
        $this->image($p, ['path' => 'C:\\private\\image.png']);
        $r = $this->get('/api/v1/products/'.$p->slug);
        $r->assertOk()->assertJsonPath('data.images.0.url', null);
        $this->assertStringNotContainsString('private', $r->getContent());
    }

    public function test_absent_image_metadata_returns_null_without_placeholder_or_filesystem_lookup(): void
    {
        $p = $this->product();
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.primary_image', null)->assertJsonPath('data.images', []);
        $this->assertNull(CatalogImageUrl::forPath(null));
    }
}
