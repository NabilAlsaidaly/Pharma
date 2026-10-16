<?php

namespace Tests\Feature\Gifts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\GiftTestCase;

class GiftDirectoryTest extends GiftTestCase
{
    public function test_owner_can_reach_products_beyond_first_fifty_even_with_identical_names(): void
    {
        $brand = $this->brand();
        for ($i = 0; $i < 51; $i++) {
            $this->product(['name' => 'منتج متكرر الاسم', 'slug' => 'directory-'.$i], $brand);
        }
        $last = DB::table('products')->orderBy('name')->orderBy('id')->offset(50)->value('id');
        $this->ownerLogin();
        $this->get(route('owner.gifts.create', ['catalog_q' => 'منتج متكرر الاسم']))->assertOk()->assertDontSee($last.'|', false);
        $this->get(route('owner.gifts.create', ['catalog_q' => 'منتج متكرر الاسم', 'catalog_page' => 2]))->assertOk()->assertSee($last.'|', false);
        $this->get(route('owner.gifts.create', ['catalog_page' => 1001]))->assertStatus(422);
    }

    public function test_owner_can_reach_occasions_beyond_first_hundred_without_losing_current_selection(): void
    {
        $rows = [];
        for ($i = 0; $i < 101; $i++) {
            $rows[] = ['id' => (string) Str::uuid7(), 'name' => 'مناسبة متكررة', 'is_active' => true, 'sort_order' => 0, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('gift_occasions')->insert($rows);
        $last = DB::table('gift_occasions')->orderBy('sort_order')->orderBy('id')->offset(100)->value('id');
        $this->ownerLogin();
        $this->get(route('owner.gifts.create'))->assertOk()->assertDontSee($last);
        $this->get(route('owner.gifts.create', ['occasion_page' => 2]))->assertOk()->assertSee($last);
        $id = $this->gift(['occasion_ids' => [$last]], false);
        $this->get(route('owner.gifts.edit', ['id' => $id]))->assertOk()->assertSee($last);
        $this->get(route('owner.gifts.create', ['occasion_page' => 1001]))->assertStatus(422);
    }
}
