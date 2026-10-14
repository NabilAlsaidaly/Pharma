<?php

namespace App\Domains\ManagementDashboard\Infrastructure\ReadModels;

use Illuminate\Support\Facades\DB;

class DashboardSchema
{
    private ?array $tables = null;

    public function has(string ...$tables): bool
    {
        $this->tables ??= DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())
            ->whereIn('TABLE_NAME', ['products', 'brands', 'concerns', 'customers', 'checkout_intents', 'checkout_intent_items', 'product_variants', 'concern_product'])
            ->select('TABLE_NAME as name')->pluck('name')->all();

        return array_diff($tables, $this->tables) === [];
    }

    public function require(string ...$tables): void
    {
        abort_unless($this->has(...$tables), 503, 'المعلومات غير متاحة حالياً. يلزم تجهيز البيئة بواسطة المشغّل.');
    }
}
