<?php

namespace Tests\Feature\Gifts;

use Illuminate\Support\Facades\DB;
use Tests\GiftTestCase;

class GiftVersionBoundaryTest extends GiftTestCase
{
    public function test_final_unsigned_publication_can_be_requested_and_read_without_overflowing_owner_writes(): void
    {
        $id = $this->gift();
        $max = '18446744073709551615';
        DB::table('gift_packages')->where('id', $id)->update(['published_version' => null]);
        DB::table('gift_package_revisions')->where('package_id', $id)->where('version', '3')->update(['version' => $max]);
        DB::table('gift_packages')->where('id', $id)->update(['lock_version' => $max, 'published_version' => $max]);
        $slug = DB::table('gift_packages')->where('id', $id)->value('slug');
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertOk()->assertJsonPath('data.published_version', $max);
        $this->actingAs($this->customer(), 'customer');
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id))->assertCreated();
        $this->ownerLogin();
        $this->get(route('owner.gifts.edit', ['id' => $id, 'revision' => $max]))->assertOk()->assertSee($max);
        $this->post(route('owner.gifts.mutate', ['id' => $id]), $this->giftCommand($max, 'unpublish'))->assertStatus(422);
        $this->assertDatabaseCount('gift_package_revisions', 3);
        $this->assertDatabaseCount('gift_requests', 1);
    }
}
