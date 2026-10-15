<?php

namespace App\Domains\Catalog\Application\Administration;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use App\Domains\Catalog\Interfaces\Http\CatalogImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ArchiveCatalogRecord
{
    public function execute(string $type, string $id, string $version, bool $restore, CatalogChange $change): Model
    {
        return DB::transaction(function () use ($type, $id, $version, $restore, $change): Model {
            $initial = CatalogRecords::model($type)->newQuery()->withTrashed()->findOrFail($id);
            if (in_array($type, ['variants', 'images'], true)) {
                Product::query()->whereKey($initial->getAttribute('product_id'))->lockForUpdate()->firstOrFail();
            }
            $record = CatalogRecords::model($type)->newQuery()->withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            CatalogRecords::version($record, $version);
            if ($restore !== ($record->getAttribute('deleted_at') !== null)) {
                return $record; // Repeating an already satisfied action with current version is harmless.
            }
            if ($restore && $record instanceof Product) {
                Brand::withTrashed()->whereKey($record->brand_id)->lockForUpdate()->firstOrFail();
                abort_if(($record->price_usd !== null || $record->price_syp !== null) && $record->variants()->withTrashed()->exists(), 409, 'تعارض في ملكية السعر.');
            }
            if ($restore && $record instanceof ProductVariant) {
                $product = Product::query()->findOrFail($record->product_id);
                abort_if($product->price_usd !== null || $product->price_syp !== null, 409, 'يجب إخلاء السعر الرئيسي قبل استعادة الخيار.');
            }
            if ($restore && $record instanceof ProductImage) {
                abort_if(ProductImage::query()->where('product_id', $record->product_id)->count() >= 20, 409, 'الحد الأقصى عشرون صورة حالية للمنتج.');
                abort_unless(CatalogImageUrl::forPath($record->path) !== null && Storage::disk('public')->exists($record->path), 409, 'ملف الصورة غير متاح للاستعادة.');
            }
            $before = ['deleted_at' => $record->getAttribute('deleted_at'), 'lock_version' => $record->getAttribute('lock_version')];
            if ($record instanceof ProductImage) {
                $before['is_primary'] = $record->is_primary;
                $record->is_primary = false;
            }
            CatalogRecords::advance($record);
            // Save version/primary change while still in its existing archive scope.
            $record->save();
            if ($restore) {
                $record->restore();
            } else {
                $record->delete();
            }
            $change->record($type, $record, $restore ? 'restore' : 'archive', $before, ['deleted_at' => $record->getAttribute('deleted_at'), 'lock_version' => $record->getAttribute('lock_version')] + ($record instanceof ProductImage ? ['is_primary' => false] : []));

            return $record;
        }, 3);
    }
}
