<?php

namespace App\Domains\Catalog\Interfaces\Http;

use Illuminate\Support\Facades\Storage;

final class CatalogImageUrl
{
    public static function forPath(?string $path): ?string
    {
        if (! $path || strlen($path) > 2048 || str_contains($path, '..') || str_contains($path, '\\')
            || preg_match('/[\x00-\x20\x7f%:#?]/', $path) || str_starts_with($path, '/')
            || preg_match('/\.(?:php\d*|phtml|phar|cgi|pl|exe|sh|html?|svg)(?:\.|$)/i', $path)
            || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
