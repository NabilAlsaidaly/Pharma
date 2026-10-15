<?php

namespace Tests\Feature\OwnerDashboard;

use App\Domains\ManagementDashboard\Application\DashboardDateRange;
use App\Domains\ManagementDashboard\Infrastructure\ReadModels\DashboardMetrics;
use App\Domains\ManagementDashboard\Infrastructure\ReadModels\DashboardSchema;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\OwnerDashboardTestCase;

class OwnerDashboardMetricsTest extends OwnerDashboardTestCase
{
    public function test_actual_sql_failure_is_safe_500_without_fake_zero_metrics_or_sensitive_log_context(): void
    {
        $this->ownerLogin();
        $metrics = \Mockery::mock(DashboardMetrics::class);
        $metrics->shouldReceive('read')->once()->andReturnUsing(fn () => DB::table('products')->select('private_failure_sentinel')->first());
        $this->app->instance(DashboardMetrics::class, $metrics);
        Log::spy();
        $this->get(route('owner.dashboard'))->assertStatus(500)->assertDontSee('private_failure_sentinel')->assertDontSee('SQLSTATE')->assertDontSee('إجمالي المنتجات');
        Log::shouldHaveReceived('error')->with('owner_portal_failed', \Mockery::on(fn ($context) => array_keys($context) === ['exception_type', 'request_id'] && $context['exception_type'] === QueryException::class))->once();
    }

    public function test_empty_metrics_are_real_zeros_and_visitor_analytics_is_unavailable(): void
    {
        $this->ownerLogin();
        $response = $this->get(route('owner.dashboard'))->assertOk()->assertSee('لم يتم تفعيل تتبع الزيارات بعد.');
        $metrics = $response->viewData('metrics');
        foreach ($metrics as $value) {
            $this->assertSame(0, $value);
        }
        $this->assertCount(7, $response->viewData('days'));
        $response->assertSee('لم تُسجّل طلبات مبدئية')->assertDontSee('إيرادات');
    }

    public function test_real_counts_dates_and_chart_do_not_change_historical_request_facts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));
        $one = $this->requestFixture();
        $two = $this->requestFixture();
        $three = $this->requestFixture();
        DB::table('checkout_intents')->where('id', $one)->update(['created_at' => '2026-10-14 00:00:00']);
        DB::table('checkout_intents')->where('id', $two)->update(['created_at' => '2026-10-13 23:59:59']);
        DB::table('checkout_intents')->where('id', $three)->update(['created_at' => '2026-10-15 00:00:00']);
        $this->product(['status' => 'hidden']);
        $this->concern();
        $this->ownerLogin();
        $response = $this->get(route('owner.dashboard', ['period' => 'today']))->assertOk();
        $this->assertSame([
            'products' => 2,
            'active' => 1,
            'hidden' => 1,
            'archived_products' => 0,
            'brands' => 2,
            'archived_brands' => 0,
            'concerns' => 1,
            'archived_concerns' => 0,
            'customers' => 1,
            'requests' => 3,
            'range_requests' => 1,
        ], $response->viewData('metrics'));
        $this->assertSame([['date' => '2026-10-14', 'count' => 1]], $response->viewData('days'));
        $response->assertSee('عرض القيم اليومية كنص')->assertDontSee('Private-address-sentinel')->assertDontSee('private-contact-sentinel@example.test');
        $custom = $this->get(route('owner.dashboard', ['period' => 'custom', 'from' => '2026-10-13', 'to' => '2026-10-14']))->assertOk();
        $this->assertSame(2, $custom->viewData('metrics')['range_requests']);
        $this->assertSame([1, 1], array_column($custom->viewData('days'), 'count'));
        $this->travelBack();
    }

    public function test_configured_timezone_uses_utc_storage_and_dst_safe_inclusive_exclusive_buckets(): void
    {
        config(['app.timezone' => 'Asia/Damascus']);
        $range = DashboardDateRange::from(['period' => 'custom', 'from' => '2026-10-13', 'to' => '2026-10-13']);
        $this->assertSame('2026-10-12 21:00:00', $range->buckets()[0]['start']);
        $this->assertSame('2026-10-13 21:00:00', $range->buckets()[0]['end']);
        $one = $this->requestFixture();
        $two = $this->requestFixture();
        DB::table('checkout_intents')->where('id', $one)->update(['created_at' => '2026-10-12 21:00:00']);
        DB::table('checkout_intents')->where('id', $two)->update(['created_at' => '2026-10-13 21:00:00']);
        $this->assertSame(1, app(DashboardMetrics::class)->read($range)['metrics']['range_requests']);
        config(['app.timezone' => 'America/New_York']);
        $dst = DashboardDateRange::from(['period' => 'custom', 'from' => '2026-03-08', 'to' => '2026-03-08'])->buckets()[0];
        $this->assertSame('2026-03-08 05:00:00', $dst['start']);
        $this->assertSame('2026-03-09 04:00:00', $dst['end']);
    }

    public function test_missing_request_schema_is_unavailable_not_zero_and_missing_list_schema_is_safe_503(): void
    {
        $schema = \Mockery::mock(DashboardSchema::class)->makePartial();
        $schema->shouldReceive('has')->with('checkout_intents')->andReturn(false);
        $schema->shouldReceive('require')->with('checkout_intents')->andThrow(new HttpException(503, 'fixture missing schema'));
        $this->app->instance(DashboardSchema::class, $schema);
        $this->ownerLogin();
        $response = $this->get(route('owner.dashboard'))->assertOk();
        $this->assertNull($response->viewData('metrics')['requests']);
        $this->assertNull($response->viewData('metrics')['range_requests']);
        $this->assertNull($response->viewData('days'));
        $response->assertSee('غير متاح');
        $this->get(route('owner.requests.index'))->assertStatus(503)->assertDontSee('fixture missing schema')->assertDontSee('SQLSTATE');
    }

    public static function invalidRanges(): array
    {
        return [
            'unknown' => [['period' => 'forever']],
            'incomplete' => [['period' => 'custom', 'from' => '2026-10-01']],
            'inverted' => [['period' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01']],
            'too-long' => [['period' => 'custom', 'from' => '2025-01-01', 'to' => '2026-10-01']],
            'bad-format' => [['period' => 'custom', 'from' => 'bad', 'to' => '2026-10-01']],
            'unknown-field' => [['revenue' => 'paid']],
            'unexpected-dates' => [['period' => 'today', 'from' => '2026-10-01', 'to' => '2026-10-02']],
        ];
    }

    #[DataProvider('invalidRanges')]
    public function test_date_input_is_bounded_and_does_not_render_technical_errors(array $query): void
    {
        $this->ownerLogin();
        $this->get(route('owner.dashboard', $query))->assertUnprocessable()->assertSee('يرجى مراجعة خيارات العرض')->assertDontSee('SQLSTATE');
    }
}
