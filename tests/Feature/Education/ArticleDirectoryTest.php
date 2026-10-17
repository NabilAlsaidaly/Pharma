<?php

namespace Tests\Feature\Education;

use App\Domains\Content\Infrastructure\Education\OwnerEducationReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\EducationTestCase;

class ArticleDirectoryTest extends EducationTestCase
{
    public function test_all_three_bounded_directories_reach_records_after_the_first_page(): void
    {
        $brand = $this->brand();
        for ($i = 0; $i < 51; $i++) {
            $this->product(['name' => 'اسم متكرر'], $brand);
            $this->concern(['name' => 'اسم متكرر']);
            DB::table('educational_categories')->insert(['id' => (string) Str::uuid7(), 'slug' => 'directory-'.$i, 'name' => 'اسم متكرر', 'is_active' => true, 'sort_order' => 0, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $reader = app(OwnerEducationReader::class);
        $first = $reader->article(null, []);
        $second = $reader->article(null, ['product_page' => 2, 'concern_page' => 2, 'category_page' => 2]);
        foreach (['product', 'concern', 'category'] as $kind) {
            $this->assertCount(50, $first['choices'][$kind]);
            $this->assertCount(1, $second['choices'][$kind]);
            $this->assertFalse($first['choices'][$kind]->pluck('id')->contains($second['choices'][$kind][0]->id));
        }
        $this->assertDatabaseCount('educational_content_events', 0);
    }
}
