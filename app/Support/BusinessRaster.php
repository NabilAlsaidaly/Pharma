<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BusinessRaster
{
    public function prepare(UploadedFile $file): array
    {
        abort_unless(extension_loaded('gd'), 503);
        abort_unless($file->isValid() && $file->getSize() <= 5 * 1024 * 1024, 422, 'الحد الأقصى للصورة 5 ميغابايت.');
        $name = $file->getClientOriginalPath();
        abort_if(preg_match('/[\\\\\/\x00-\x1f]|\.(php\d*|phtml|phar|html?|svg|exe|cgi|sh)(\.|$)/i', $name), 422, 'اسم الملف غير آمن.');
        $info = @getimagesize($file->getPathname());
        abort_unless($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)
            && $info[0] <= 4096 && $info[1] <= 4096 && $info[0] * $info[1] <= 4194304, 422, 'الصورة غير سليمة أو أبعادها أكبر من الحد.');
        $extensions = [IMAGETYPE_JPEG => ['jpg', 'jpeg'], IMAGETYPE_PNG => ['png'], IMAGETYPE_WEBP => ['webp'], IMAGETYPE_GIF => ['gif']];
        $bytes = file_get_contents($file->getPathname());
        abort_unless(in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $extensions[$info[2]], true)
            && $file->getMimeType() === $info['mime'] && ! preg_match('/<\?(?:php|=)|<script\b/i', $bytes), 422, 'محتوى الملف لا يطابق صورة آمنة.');
        $image = @imagecreatefromstring($bytes);
        abort_unless($image !== false, 422);
        ob_start();
        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $ok = imagepng($image);
            $encoded = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
        abort_unless($ok && is_string($encoded) && strlen($encoded) <= 8 * 1024 * 1024, 422);

        return ['bytes' => $encoded, 'digest' => hash('sha256', $encoded)];
    }

    public function store(array $prepared): string
    {
        $path = 'storefront/'.Str::uuid7().'.png';
        abort_unless(Storage::disk('local')->put($path, $prepared['bytes']), 503, 'تعذر حفظ الصورة.');

        return $path;
    }

    public static function url(?string $path): ?string
    {
        abort_if($path !== null && ! preg_match('#^storefront/[a-f0-9-]{36}\.png$#D', $path), 503);

        return $path === null ? null : url('/api/v1/storefront/media/'.basename($path));
    }

    public static function previewUrl(string $path): string
    {
        self::url($path);

        return route('owner.settings.media', ['id' => pathinfo($path, PATHINFO_FILENAME)]);
    }

    public static function response(string $id)
    {
        abort_unless(Str::isUuid($id), 404);
        $path = 'storefront/'.$id.'.png';
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
