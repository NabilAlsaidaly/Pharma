<?php

namespace Tests\Feature\StoreConfiguration;

use App\Domains\Settings\Application\ManageStoreSettings;
use App\Support\BusinessRaster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\StoreConfigurationTestCase;

class StoreMediaPrivacyTest extends StoreConfigurationTestCase
{
    public function test_draft_bytes_are_private_and_only_current_published_references_admit_public_reads(): void
    {
        Storage::fake('local');
        $id = $this->setting();
        $service = app(ManageStoreSettings::class);
        $service->execute('profile', $id, $this->owner, $this->command([], '1', 'media'), UploadedFile::fake()->image('draft.png'));
        $old = DB::table('store_profiles')->sole()->image_path;
        $public = BusinessRaster::url($old);
        $private = BusinessRaster::previewUrl($old);
        $this->get($private)->assertRedirect(route('owner.login'));
        $this->actingAs($this->customer(), 'customer');
        $this->get($private)->assertRedirect(route('owner.login'));
        $this->getJson($public)->assertNotFound();
        $this->assertContains($this->get('/storage/'.$old)->status(), [403, 404]);
        $this->assertFileDoesNotExist(public_path('storage/'.$old));
        $this->ownerLogin();
        $this->get($private)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->setting(values: [], version: '2', action: 'publish');
        Auth::guard('owner')->logout();
        $this->get($public)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($private)->assertRedirect(route('owner.login'));
        $this->ownerLogin();
        $service->execute('profile', $id, $this->owner, $this->command([], '3', 'media'), UploadedFile::fake()->image('new-draft.png', 30, 20));
        $next = DB::table('store_profiles')->sole()->image_path;
        $this->getJson(BusinessRaster::url($next))->assertNotFound();
        $this->get($public)->assertOk();
        $this->setting(values: [], version: '4', action: 'publish');
        $this->get(BusinessRaster::url($next))->assertOk();
        $this->getJson($public)->assertNotFound();
        Storage::disk('local')->assertExists($old);
        $this->get($private)->assertOk();
        $this->setting(values: [], version: '5', action: 'unpublish');
        $this->getJson(BusinessRaster::url($next))->assertNotFound();
        $this->get(BusinessRaster::previewUrl($next))->assertOk();
        $this->assertDatabaseCount('store_profiles_revisions', 6);
    }
}
