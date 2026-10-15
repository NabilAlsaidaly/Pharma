<?php

namespace App\Domains\Catalog\Application\Administration;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

final class CatalogRecords
{
    public const TYPES = ['products' => Product::class, 'brands' => Brand::class, 'concerns' => Concern::class, 'variants' => ProductVariant::class, 'images' => ProductImage::class];

    public const LABELS = ['products' => 'المنتجات', 'brands' => 'العلامات التجارية', 'concerns' => 'الاهتمامات', 'variants' => 'خيارات المنتجات', 'images' => 'صور المنتجات'];

    public static function model(string $type): Product|Brand|Concern|ProductVariant|ProductImage
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return new (self::TYPES[$type]);
    }

    public static function version(Model $record, string $expected): void
    {
        abort_unless((string) $record->getAttribute('lock_version') === $expected, 409, 'تغيّر السجل منذ فتح الصفحة. حدّثي الصفحة وراجعي التعديلات.');
    }

    public static function advance(Model $record): void
    {
        $value = (string) $record->getAttribute('lock_version');
        abort_if(strlen($value) > 15, 409, 'بلغ السجل الحد التقني للإصدارات.');
        $record->setAttribute('lock_version', (int) $value + 1);
    }
}
