<?php

namespace App\Domains\Delivery\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DeliveryWorkers
{
    public static function ready(): void
    {
        abort_unless(Schema::hasTable('delivery_workers') && Schema::hasTable('delivery_worker_changes'), 503, 'دليل التوصيل غير متاح قبل تجهيز قاعدة البيانات.');
    }

    public function listing(array $filters)
    {
        self::ready();
        $query = DB::table('delivery_workers');
        if (isset($filters['active']) && $filters['active'] !== '') {
            $query->where('is_active', $filters['active'] === '1');
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $q = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($s) => $s->whereRaw("name LIKE ? ESCAPE '!'", [$q])->orWhereRaw("phone LIKE ? ESCAPE '!'", [$q]));
        }

        return $query->orderBy('name')->orderBy('id')->paginate(25, ['id', 'name', 'phone', 'is_active', 'lock_version'], 'page', $filters['page'] ?? 1);
    }

    public function worker(string $id): object
    {
        self::ready();
        $worker = DB::table('delivery_workers')->where('id', $id)->first(['id', 'name', 'phone', 'notes', 'is_active', 'lock_version', 'updated_at']);
        abort_unless($worker !== null, 404);

        return $worker;
    }

    public function history(array $filters)
    {
        self::ready();
        if (isset($filters['worker'])) {
            abort_unless(DB::table('delivery_workers')->where('id', $filters['worker'])->exists(), 404);
        }

        return DB::table('delivery_worker_changes as c')->join('management_users as u', 'u.id', '=', 'c.owner_id')
            ->when(isset($filters['worker']), fn ($q) => $q->where('c.worker_id', $filters['worker']))
            ->orderByDesc('c.created_at')->orderByDesc('c.id')->paginate(25, ['c.worker_id', 'c.action', 'c.previous_version', 'c.new_version', 'c.changed_fields', 'c.created_at', 'u.display_name'], 'page', $filters['page'] ?? 1);
    }
}
