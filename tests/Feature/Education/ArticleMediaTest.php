<?php

namespace Tests\Feature\Education;

use App\Domains\Content\Application\Education\ManageEducationalArticle;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\EducationTestCase;

class ArticleMediaTest extends EducationTestCase
{
    public function test_inline_and_cover_are_revision_bound_and_private_until_explicit_publication(): void
    {
        $id = $this->article(publish: false);
        $cover = $this->articleImage($id, 'cover');
        $inline = $this->articleImage($id);
        $this->get('/api/v1/education/media/'.$cover.'.png')->assertNotFound();
        $this->refused(fn () => $this->change($id, 'publish')); // unplaced image
        $body = $this->document();
        $body['blocks'][] = ['type' => 'image', 'media_id' => $inline, 'caption' => 'تعليق محفوظ'];
        $this->change($id, 'save', ['body' => $body]);
        $this->change($id, 'publish');
        $slug = DB::table('educational_articles')->where('id', $id)->value('slug');
        $this->getJson('/api/v1/education/articles/'.$slug)->assertOk()->assertJsonPath('data.cover.id', $cover)->assertJsonPath('data.body.blocks.1.image.id', $inline)->assertJsonPath('data.body.blocks.1.image.width', 20)->assertJsonMissingPath('data.body.blocks.1.media_id');
        $this->get('/api/v1/education/media/'.$inline.'.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->change($id, 'save', ['body' => $this->document(), 'images' => []]);
        $this->get('/api/v1/education/media/'.$inline.'.png')->assertOk();
        $this->change($id, 'publish');
        $this->get('/api/v1/education/media/'.$inline.'.png')->assertNotFound();
        $this->get('/api/v1/education/media/'.$cover.'.png')->assertNotFound();
        Storage::disk('local')->assertExists('education/'.$inline.'.png');
        $this->ownerLogin();
        $this->get(route('owner.education.media', ['id' => $inline]))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get(route('owner.education.edit', ['id' => $id, 'revision' => '5']))->assertOk()->assertSee('تعليق محفوظ');
    }

    public function test_missing_alt_bad_raster_capacity_and_image_membership_are_enforced_atomically(): void
    {
        $id = $this->article(publish: false);
        $this->refused(fn () => $this->change($id, 'media', ['role' => 'inline', 'alt_text' => ''], UploadedFile::fake()->image('image.png')));
        $this->refused(fn () => $this->change($id, 'media', ['role' => 'inline', 'alt_text' => 'صورة'], UploadedFile::fake()->createWithContent('x.svg', '<svg><script>x</script></svg>')));
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->articleImage($id);
        }
        $this->refused(fn () => $this->articleImage($id));
        $this->assertCount(5, Storage::disk('local')->allFiles('education'));
        $cover = $this->articleImage($id, 'cover');
        $this->refused(fn () => $this->articleImage($id, 'cover'));
        $this->assertCount(6, Storage::disk('local')->allFiles('education'));
        $body = $this->document();
        $body['blocks'][] = ['type' => 'image', 'media_id' => $cover];
        $this->refused(fn () => $this->change($id, 'save', ['body' => $body]));
        $other = $this->article(publish: false);
        $this->refused(fn () => $this->change($other, 'save', ['images' => [['media_id' => $ids[0], 'role' => 'inline', 'alt_text' => 'انتحال']]]));
        $this->assertSame('7', $this->version($id));
        $this->assertDatabaseCount('educational_media', 6);
    }

    public function test_sql_failure_compensates_fresh_media_and_owner_retry_does_not_duplicate_file(): void
    {
        $id = $this->article(publish: false);
        $file = UploadedFile::fake()->image('safe.png', 20, 20);
        $command = $this->command('1', 'media', ['role' => 'cover', 'alt_text' => 'غلاف']);
        $service = app(ManageEducationalArticle::class);
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'insert into `educational_content_events`')) {
                $armed = false;
                // A genuinely invalid MySQL insert after bytes/revision/event writes; no implicit-commit DDL.
                DB::table('educational_media')->insert(['digest' => 'failure-sentinel']);
            }
        });
        try {
            $service->execute($id, $this->owner, $command, $file);
            $this->fail('Expected actual SQL failure.');
        } catch (QueryException) {
            $this->assertSame('1', $this->version($id));
            $this->assertDatabaseCount('educational_media', 0);
            $this->assertCount(0, Storage::disk('local')->allFiles('education'));
        }
        $service->execute($id, $this->owner, $command, $file);
        $service->execute($id, $this->owner, $command, $file);
        $this->assertSame('2', $this->version($id));
        $this->assertDatabaseCount('educational_media', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('education'));
    }

    public function test_unpublication_revokes_all_public_images_but_retains_private_history(): void
    {
        $id = $this->article(publish: false);
        $image = $this->articleImage($id, 'cover');
        $this->change($id, 'publish');
        $this->get('/api/v1/education/media/'.$image.'.png')->assertOk();
        $this->change($id, 'unpublish');
        $this->get('/api/v1/education/media/'.$image.'.png')->assertNotFound();
        $this->change($id, 'archive');
        $this->change($id, 'restore');
        $this->get('/api/v1/education/media/'.$image.'.png')->assertNotFound();
        Storage::disk('local')->assertExists('education/'.$image.'.png');
    }
}
