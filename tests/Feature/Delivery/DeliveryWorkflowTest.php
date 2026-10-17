<?php

namespace Tests\Feature\Delivery;

use App\Domains\Delivery\Application\ManageDeliveryWorker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\DeliveryTestCase;

class DeliveryWorkflowTest extends DeliveryTestCase
{
    public function test_zone_publication_uses_exact_old_snapshot_and_no_private_fields(): void
    {
        $id = $this->zone(publish: false);
        $this->getJson('/api/v1/delivery/zones')->assertOk()->assertJsonCount(0, 'data');
        $this->setting('zones', [], '1', $id, 'publish');
        $r = $this->getJson('/api/v1/delivery/zones')->assertOk()->assertJsonPath('data.0.revision', '2')->assertJsonPath('data.0.fee_usd', null)->assertJsonPath('data.0.fee_syp', '0.00')->assertJsonPath('data.0.manual_confirmation_required', true)->assertJsonPath('data.0.fees_are_informational', true);
        $this->assertSame(['id', 'revision', 'name', 'governorate', 'city', 'fee_usd', 'fee_syp', 'fees_are_informational', 'manual_confirmation_required'], array_keys($r->json('data.0')));
        $r->assertDontSee('private-zone-conditions-sentinel')->assertDontSee('owner_id')->assertHeader('X-Request-ID');
        $this->setting('zones', ['name' => 'مسودة خاصة', 'fee_usd' => '9999999999999999.99', 'fee_syp' => null], '2', $id);
        $this->getJson('/api/v1/delivery/zones')->assertJsonPath('data.0.name', 'منطقة تجريبية')->assertJsonPath('data.0.fee_usd', null);
        $this->setting('zones', [], '3', $id, 'publish');
        $this->getJson('/api/v1/delivery/zones')->assertJsonPath('data.0.revision', '4')->assertJsonPath('data.0.fee_usd', '9999999999999999.99')->assertJsonPath('data.0.fee_syp', null);
        $this->getJson('/api/v1/storefront/configuration')->assertDontSee('fee_usd');
        $this->assertDatabaseHas('store_delivery_zones_revisions', ['record_id' => $id, 'version' => 2, 'fee_syp' => '0.00', 'fee_usd' => null]);
    }

    public function test_broad_narrow_zones_are_independent_no_fallback_and_archive_restore_unpublished(): void
    {
        $broad = $this->zone(['sort_order' => '2', 'fee_usd' => '4.50']);
        $narrow = $this->zone(['city' => 'مدينة اختبار', 'fee_usd' => null, 'fee_syp' => null, 'sort_order' => '1']);
        $this->getJson('/api/v1/delivery/zones')->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $narrow)->assertJsonPath('data.0.fee_usd', null)->assertJsonPath('data.1.id', $broad)->assertJsonPath('data.1.city', null);
        $this->setting('zones', [], '2', $narrow, 'archive');
        $this->getJson('/api/v1/delivery/zones')->assertJsonCount(1, 'data');
        $this->setting('zones', [], '3', $narrow, 'restore');
        $this->getJson('/api/v1/delivery/zones')->assertJsonCount(1, 'data');
        $this->setting('zones', [], '2', $broad, 'unpublish');
        $this->getJson('/api/v1/delivery/zones')->assertJsonCount(0, 'data');
    }

    public function test_worker_lifecycle_durable_replay_duplicate_contacts_and_private_audit(): void
    {
        $this->ownerLogin();
        $cmd = $this->command(['name' => ' عامل عربي ', 'phone' => '+963123456789', 'notes' => 'private-worker-note', 'is_active' => true]);
        $service = app(ManageDeliveryWorker::class);
        $id = $service->execute(null, $this->owner, $cmd);
        $this->worker(['notes' => null], '1', $id);
        $this->worker([], '2', $id, 'disable');
        $this->worker([], '3', $id, 'enable');
        $this->assertSame($id, $service->execute(null, $this->owner, $cmd));
        $this->assertDatabaseCount('delivery_worker_changes', 4);
        $this->assertDatabaseHas('delivery_workers', ['id' => $id, 'lock_version' => 4, 'name' => 'عامل عربي', 'notes' => null, 'is_active' => true]);
        $this->worker($cmd['values']);
        $this->assertDatabaseCount('delivery_workers', 2);
        $history = DB::table('delivery_worker_changes')->orderBy('created_at')->get()->toJson();
        $this->assertStringNotContainsString('private-worker-note', $history);
        $this->assertStringNotContainsString('+963123456789', $history);
        $this->get(route('owner.delivery.history', ['worker' => $id]))->assertOk()->assertSee('تعطيل')->assertSee('إعادة تفعيل');
        $this->getJson('/api/v1/delivery/zones')->assertDontSee('عامل عربي')->assertDontSee('phone');
    }

    public function test_stale_changed_operation_and_noop_activation_are_conflicts(): void
    {
        $this->ownerLogin();
        $id = $this->worker();
        $url = route('owner.delivery.mutate', ['id' => $id]);
        $cmd = $this->command(['name' => 'عامل معدل'], '1');
        $this->post($url, $cmd)->assertRedirect();
        $this->post($url, $this->command(['name' => 'عنوان قديم'], '1'))->assertStatus(409);
        $cmd['values']['name'] = 'تغيير نفس العملية';
        $this->post($url, $cmd)->assertStatus(409);
        $this->post($url, $this->command([], '2', 'enable'))->assertStatus(409);
        $this->assertDatabaseCount('delivery_worker_changes', 2);
    }

    public function test_directory_search_is_literal_bounded_filtered_and_forms_preserve_notes(): void
    {
        $this->ownerLogin();
        $id = $this->worker(['name' => 'عامل%_تجريبي', 'phone' => '+963123456789', 'is_active' => false]);
        for ($i = 0; $i < 26; $i++) {
            $this->worker(['name' => 'عامل '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'phone' => '123456', 'is_active' => true]);
        }
        $this->get(route('owner.delivery.index', ['q' => '%_']))->assertOk()->assertSee('عامل%_تجريبي')->assertDontSee('عامل 000');
        $this->get(route('owner.delivery.index', ['active' => '0']))->assertOk()->assertSee('عامل%_تجريبي')->assertDontSee('عامل 000');
        $this->get(route('owner.delivery.index', ['active' => '1', 'page' => 2]))->assertOk()->assertSee('عامل 025')->assertDontSee('عامل 000');
        $this->get(route('owner.delivery.edit', ['id' => $id]))->assertOk()->assertSee('إعادة التفعيل')->assertSee('الهاتف');
        $this->post(route('owner.delivery.mutate', ['id' => $id]), $this->command(['notes' => 'نص عربي خاص', 'is_active' => '1'], '1'))->assertRedirect();
        $this->get(route('owner.delivery.edit', ['id' => $id]))->assertSee('نص عربي خاص');
    }

    public function test_sql_failure_rolls_back_contact_and_event_together_and_constraints_are_real(): void
    {
        $id = $this->worker();
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'update `delivery_workers`')) {
                $armed = false;
                DB::table('delivery_worker_changes')->insert(['request_id' => 'actual-invalid-dml']);
            }
        });
        try {
            $this->worker(['name' => 'يفشل حفظه'], '1', $id);
            $this->fail('Expected real SQL insert failure.');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->errorInfo);
        }
        $this->assertDatabaseHas('delivery_workers', ['id' => $id, 'name' => 'عامل تجريبي', 'lock_version' => 1]);
        $this->assertDatabaseCount('delivery_worker_changes', 1);
        foreach ([fn () => DB::table('delivery_workers')->where('id', $id)->update(['lock_version' => 0]), fn () => DB::table('delivery_workers')->where('id', $id)->update(['is_active' => 2]), fn () => DB::table('delivery_workers')->where('id', $id)->delete()] as $operation) {
            try {
                $operation();
                $this->fail('Expected SQL constraint.');
            } catch (QueryException $e) {
                $this->assertNotEmpty($e->errorInfo);
            }
        }
        $event = (array) DB::table('delivery_worker_changes')->sole();
        $event['id'] = (string) Str::uuid7();
        try {
            DB::table('delivery_worker_changes')->insert($event);
            $this->fail('Operation and version uniqueness are SQL enforced.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
        }
    }

    public function test_unsigned_max_version_remains_readable_but_cannot_advance(): void
    {
        $id = $this->worker();
        DB::table('delivery_workers')->where('id', $id)->update(['lock_version' => '18446744073709551615']);
        $this->ownerLogin();
        $this->get(route('owner.delivery.edit', ['id' => $id]))->assertOk()->assertSee('18446744073709551615');
        $this->post(route('owner.delivery.mutate', ['id' => $id]), $this->command(['name' => 'لا يتغير'], '18446744073709551615'))->assertUnprocessable();
        $this->assertDatabaseHas('delivery_workers', ['id' => $id, 'lock_version' => '18446744073709551615', 'name' => 'عامل تجريبي']);
        $this->assertDatabaseCount('delivery_worker_changes', 1);
    }
}
