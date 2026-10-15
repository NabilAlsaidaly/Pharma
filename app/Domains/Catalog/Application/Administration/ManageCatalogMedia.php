<?php

namespace App\Domains\Catalog\Application\Administration;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ManageCatalogMedia
{
    public function upload(string $type, string $id, string $version, UploadedFile $file, CatalogChange $change): void
    {
        abort_unless(in_array($type, ['products', 'brands'], true), 404);
        abort_unless(extension_loaded('gd'), 503, 'معالجة الصور غير متاحة حالياً.');
        $name = $file->getClientOriginalPath();
        abort_if(preg_match('/[\\\\\/\x00-\x1f]|\.(php\d*|phtml|phar|html?|svg|exe|cgi|sh)(\.|$)/i', $name), 422, 'اسم الملف غير آمن.');
        abort_unless($file->isValid() && $file->getSize() <= 5 * 1024 * 1024, 422, 'الحد الأقصى للصورة 5 ميغابايت.');
        $info = @getimagesize($file->getPathname());
        abort_unless($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)
            && $info[0] <= 4096 && $info[1] <= 4096 && $info[0] * $info[1] <= 4194304, 422, 'اختاري صورة نقطية سليمة لا تتجاوز أربعة ملايين بكسل.');
        $extensions = [IMAGETYPE_JPEG => ['jpg', 'jpeg'], IMAGETYPE_PNG => ['png'], IMAGETYPE_WEBP => ['webp'], IMAGETYPE_GIF => ['gif']];
        $sourceBytes = file_get_contents($file->getPathname());
        abort_unless(in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $extensions[$info[2]], true)
            && $file->getMimeType() === $info['mime'] && ! preg_match('/<\?(?:php|=)|<script\b/i', $sourceBytes), 422, 'نوع الملف لا يطابق محتواه أو يتضمن محتوى تنفيذياً.');
        // Decode and re-encode; executable tails, animation and client metadata never reach stored bytes.
        $image = @imagecreatefromstring($sourceBytes);
        abort_unless($image !== false, 422, 'تعذر قراءة الصورة.');
        ob_start();
        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $encoded = imagepng($image);
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
        abort_unless($encoded && is_string($bytes) && strlen($bytes) <= 8 * 1024 * 1024, 422, 'تعذر تجهيز الصورة ضمن الحد الآمن.');
        $path = 'catalog/'.$type.'/'.Str::uuid7().'.png';
        abort_unless(Storage::disk('public')->put($path, $bytes), 503, 'تعذر حفظ الصورة.');
        try {
            DB::transaction(function () use ($type, $id, $version, $path, $change): void {
                if ($type === 'brands') {
                    $brand = Brand::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                    CatalogRecords::version($brand, $version);
                    $change->save('brands', $brand, 'logo', ['logo_path' => $path]);
                } else {
                    $product = Product::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                    CatalogRecords::version($product, $version);
                    abort_if($product->images()->count() >= 20, 422, 'الحد الأقصى عشرون صورة حالية للمنتج.');
                    $image = new ProductImage;
                    $change->save('images', $image, 'upload', ['product_id' => $id, 'path' => $path, 'is_primary' => false, 'sort_order' => 0]);
                    CatalogRecords::advance($product);
                    $product->save();
                }
            }, 3);
        } catch (Throwable $exception) {
            // Only our fresh generated path; retain every previously stored file.
            if (! Storage::disk('public')->delete($path)) {
                logger()->warning('catalog_upload_compensation_failed', ['request_id' => $change->requestId]);
            }
            throw $exception;
        }
    }

    public function update(string $productId, string $imageId, string $version, int $order, bool $primary, CatalogChange $change): void
    {
        DB::transaction(function () use ($productId, $imageId, $version, $order, $primary, $change): void {
            Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            $images = ProductImage::query()->where('product_id', $productId)->where(fn ($q) => $q->whereKey($imageId)->orWhere('is_primary', true))->orderBy('id')->lockForUpdate()->get();
            $target = $images->firstWhere('id', $imageId);
            abort_unless($target !== null, 404);
            CatalogRecords::version($target, $version);
            if ($primary) {
                foreach ($images as $image) {
                    if ($image->is_primary && $image->id !== $imageId) {
                        $change->save('images', $image, 'primary', ['is_primary' => false]);
                    }
                }
            }
            $change->save('images', $target, 'image', ['is_primary' => $primary, 'sort_order' => $order]);
        }, 3);
    }
}
