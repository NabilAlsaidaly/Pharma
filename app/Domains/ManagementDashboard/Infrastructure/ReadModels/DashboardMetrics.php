<?php

namespace App\Domains\ManagementDashboard\Infrastructure\ReadModels;

use App\Domains\ManagementDashboard\Application\DashboardDateRange;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    public function __construct(private DashboardSchema $schema) {}

    public function read(DashboardDateRange $range): array
    {
        $products = $this->schema->has('products')
            ? DB::table('products')->selectRaw("COUNT(*) as total, COALESCE(SUM(status = 'active'), 0) as active, COALESCE(SUM(status = 'hidden'), 0) as hidden")->first() : null;
        $metrics = ['products' => $products ? (int) $products->total : null, 'active' => $products ? (int) $products->active : null,
            'hidden' => $products ? (int) $products->hidden : null];
        foreach (['brands', 'concerns', 'customers', 'requests' => 'checkout_intents'] as $key => $table) {
            $label = is_int($key) ? $table : $key;
            $query = DB::table($table);
            if ($table === 'checkout_intents') {
                $query->where('status', 'submitted');
            }
            $metrics[$label] = $this->schema->has($table) ? $query->count() : null;
        }
        $days = null;
        $metrics['range_requests'] = null;
        if ($this->schema->has('checkout_intents')) {
            $buckets = $range->buckets();
            $clauses = [];
            $bindings = [];
            foreach ($buckets as $bucket) {
                $clauses[] = 'WHEN created_at >= ? AND created_at < ? THEN ?';
                array_push($bindings, $bucket['start'], $bucket['end'], $bucket['date']);
            }
            // Bound daily UTC intervals also work across configured-zone DST changes,
            // without assuming MySQL's named timezone tables are installed.
            $counts = DB::table('checkout_intents')->where('status', 'submitted')
                ->where('created_at', '>=', $range->start->utc()->format('Y-m-d H:i:s'))
                ->where('created_at', '<', $range->end->utc()->format('Y-m-d H:i:s'))
                ->selectRaw('CASE '.implode(' ', $clauses).' END as day, COUNT(*) as count', $bindings)
                ->groupBy('day')->pluck('count', 'day');
            $days = array_map(fn ($bucket) => ['date' => $bucket['date'], 'count' => (int) ($counts[$bucket['date']] ?? 0)], $buckets);
            $metrics['range_requests'] = array_sum(array_column($days, 'count'));
        }

        return ['metrics' => $metrics, 'days' => $days, 'range' => $range, 'chart_max' => max(array_column($days ?? [], 'count') ?: [0])];
    }
}
