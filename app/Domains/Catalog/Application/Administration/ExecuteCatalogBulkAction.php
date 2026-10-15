<?php

namespace App\Domains\Catalog\Application\Administration;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use Illuminate\Support\Facades\DB;

final class ExecuteCatalogBulkAction
{
    public const CAP = 100;

    public static function actions(string $type, bool $archived): array
    {
        if ($archived) {
            return ['restore' => 'استعادة'];
        }
        $actions = ['archive' => 'نقل إلى المحذوفات'];
        if (in_array($type, ['products', 'brands', 'concerns'], true)) {
            $actions += ['active' => 'إظهار', 'hidden' => 'إخفاء'];
        }
        if ($type === 'products') {
            $actions += ['available' => 'متاح', 'on_request' => 'عند الطلب', 'unavailable' => 'غير متاح', 'recommend' => 'موصى به', 'unrecommend' => 'إلغاء التوصية'];
        }

        return $actions;
    }

    public function execute(array $preview, CatalogChange $change): void
    {
        abort_unless(count($preview['versions']) > 0 && count($preview['versions']) <= self::CAP, 422);
        DB::transaction(function () use ($preview, $change): void {
            $type = $preview['type'];
            $ids = array_keys($preview['versions']);
            $query = CatalogRecords::model($type)->newQuery()->withTrashed()->whereKey($ids);
            if (in_array($type, ['images', 'variants'], true)) {
                $parents = (clone $query)->pluck('product_id');
                Product::query()->whereKey($parents)->orderBy('id')->lockForUpdate()->get();
            }
            $records = $query->orderBy('id')->lockForUpdate()->get();
            abort_unless($records->count() === count($ids), 409);
            if ($type === 'products') {
                // Lock all referenced Brands in their own stable order, rather than Product traversal order.
                Brand::withTrashed()->whereKey($records->pluck('brand_id'))->orderBy('id')->lockForUpdate()->get();
            }
            if ($preview['scope'] === 'matching') {
                $current = app(CatalogSelection::class)->query($type, $preview['filters'])->orderBy('id')->limit(self::CAP + 1)->lockForUpdate()->pluck('id')->all();
                sort($ids);
                abort_unless($current === $ids, 409, 'تغيّرت نتائج المرشحات. أعيدي المعاينة.');
            }
            abort_unless(isset(self::actions($type, $preview['archived'])[$preview['action']]), 422);
            foreach ($records as $record) {
                CatalogRecords::version($record, (string) $preview['versions'][$record->getKey()]);
                abort_unless(($record->getAttribute('deleted_at') !== null) === $preview['archived'], 409);
            }
            foreach ($records as $record) {
                $action = $preview['action'];
                if (in_array($action, ['archive', 'restore'], true)) {
                    app(ArchiveCatalogRecord::class)->execute($type, $record->getKey(), (string) $record->getAttribute('lock_version'), $action === 'restore', $change);
                } else {
                    $values = match ($action) {
                        'active', 'hidden' => ['status' => $action],
                        'available', 'on_request', 'unavailable' => ['availability_status' => $action],
                        'recommend', 'unrecommend' => ['is_recommended' => $action === 'recommend'],
                        default => throw new \LogicException('Unsupported Catalog action.'),
                    };
                    $change->save($type, $record, 'bulk_'.$action, $values);
                }
            }
        }, 3);
    }
}
