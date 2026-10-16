<?php

namespace Tests\Feature\StoreConfiguration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\StoreConfigurationTestCase;

class StorePerformanceTest extends StoreConfigurationTestCase
{
    private function measure(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $response = $request();
            if ($response->status() >= 300 && $response->status() < 400) {
                $response->assertRedirect();
            } else {
                $response->assertOk();
            }

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    private function grow(string $table, int $from, int $to, array $attributes): void
    {
        for ($i = $from; $i < $to; $i++) {
            $id = (string) Str::uuid7();
            $row = array_map(fn ($value) => is_string($value) ? str_replace('{n}', (string) $i, $value) : $value, $attributes);
            DB::table($table)->insert($row + ['id' => $id, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()] + ($table === 'store_delivery_zones' ? ['zone_key' => hash('sha256', 'fixture-'.$i)] : []));
            DB::table($table.'_revisions')->insert($row + ['id' => (string) Str::uuid7(), 'record_id' => $id, 'version' => 1, 'owner_id' => $this->owner->id, 'action' => 'save', 'created_at' => now()]);
            if ($table !== 'store_delivery_zones') {
                DB::table($table)->where('id', $id)->update(['published_version' => 1]);
            }
        }
    }

    public function test_ten_real_http_paths_remain_bounded_across_representative_datasets(): void
    {
        $this->ownerLogin();
        $empty = $this->measure(fn () => $this->get(route('owner.settings.index')));
        $profile = $this->setting();
        $this->setting(values: [], version: '1', action: 'publish');
        $this->setting('currency', ['first_currency' => 'USD', 'usd_label' => 'دولار', 'syp_label' => 'ليرة']);
        $this->setting('currency', [], '1', action: 'publish');
        $policy = $this->content('policies', ['title' => 'اختبار', 'slug' => 'privacy', 'body' => 'نص اختبار']);
        $this->content('policies', [], '1', $policy, 'publish');
        $counts = [];
        $previous = 0;
        $version = 2;
        foreach ([1, 20, 100] as $size) {
            $this->grow('content_banners', min($previous, 20), min($size, 20), ['title' => 'بنر {n}', 'alt_text' => 'صورة {n}', 'sort_order' => 0, 'image_path' => 'storefront/'.Str::uuid7().'.png']);
            $this->grow('content_faqs', $previous, $size, ['question' => 'سؤال {n}', 'answer' => 'إجابة مطولة '.str_repeat('نص ', 100), 'sort_order' => 0]);
            $this->grow('store_delivery_zones', $previous, $size, ['name' => 'منطقة {n}', 'governorate' => 'محافظة اختبار', 'city' => 'منطقة {n}', 'sort_order' => 0]);
            for ($i = 0; $i < 20; $i++) {
                $this->setting(values: ['description' => 'إصدار سجل '.$i], version: (string) $version++);
            }
            $this->content('policies', ['body' => 'إصدار نص جديد'], (string) DB::table('content_policies')->where('id', $policy)->value('lock_version'), $policy);
            $c = [];
            $c['overview'] = $this->measure(fn () => $this->get(route('owner.settings.index')));
            $c['edit'] = $this->measure(fn () => $this->get(route('owner.settings.group', ['group' => 'profile'])));
            $c['configuration'] = $this->measure(fn () => $this->getJson('/api/v1/storefront/configuration'));
            $c['banners'] = $this->measure(fn () => $this->getJson('/api/v1/storefront/banners'));
            $c['faqs'] = $this->measure(fn () => $this->getJson('/api/v1/storefront/faqs'));
            $c['policy'] = $this->measure(fn () => $this->getJson('/api/v1/storefront/policies/privacy'));
            $c['zones'] = $this->measure(fn () => $this->get(route('owner.settings.group', ['group' => 'zones'])));
            $c['history'] = $this->measure(fn () => $this->get(route('owner.settings.history')));
            $publishCommand = $this->command([], (string) $version++, 'publish');
            $c['publish'] = $this->measure(fn () => $this->post(route('owner.settings.mutate', ['group' => 'profile']), $publishCommand));
            $updateCommand = $this->command(['description' => 'تحديث مقاس '.$size], (string) $version++);
            $c['update'] = $this->measure(fn () => $this->post(route('owner.settings.mutate', ['group' => 'profile']), $updateCommand));
            $counts[$size] = $c;
            if ($size === 1) {
                $expected = $c;
            } else {
                $this->assertSame($expected, $c, 'SQL count must not grow with collection or revision size.');
            }
            $this->getJson('/api/v1/storefront/faqs')->assertJsonCount(min($size, 20), 'data');
            $previous = $size;
            // Leave policy draft at a fresh version while preserving its published revision.
            $policyVersion = DB::table('content_policies')->where('id', $policy)->value('lock_version');
            if ($size !== 100) {
                $this->content('policies', [], (string) $policyVersion, $policy, 'publish');
            }
        }
        $plans = ['history' => DB::select('EXPLAIN SELECT id FROM store_change_events ORDER BY created_at DESC,id DESC LIMIT 25'), 'faq_revision' => DB::select('EXPLAIN SELECT id FROM content_faqs_revisions WHERE record_id = ? ORDER BY version DESC LIMIT 25', [DB::table('content_faqs')->value('id')])];
        fwrite(STDOUT, 'STORE_HTTP_COUNTS '.json_encode(['empty_overview' => $empty, 'volumes' => $counts, 'plans' => $plans], JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
