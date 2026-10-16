<?php

namespace App\Domains\Content\Interfaces\Http;

use App\Domains\Content\Infrastructure\PublishedStoreContent;
use App\Domains\Content\Infrastructure\PublishedStoreMedia;
use App\Domains\Settings\Infrastructure\PublishedStoreConfiguration;
use App\Support\BusinessRaster;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicStorefrontController
{
    public function media(Request $request, PublishedStoreMedia $media, string $id)
    {
        abort_if($request->query() !== [], 422);
        $media->authorize($id);

        return BusinessRaster::response($id);
    }

    public function configuration(Request $request, PublishedStoreConfiguration $reader)
    {
        abort_if($request->query() !== [], 422);

        return response()->json(['success' => true, 'message' => 'معلومات المتجر المنشورة.', 'data' => $reader->read()])->header('Cache-Control', 'no-store');
    }

    public function listing(Request $request, PublishedStoreContent $reader, string $type)
    {
        abort_if(array_diff(array_keys($request->query()), ['page', 'per_page']) !== [], 422);
        $filters = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:1000'], 'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])]]);

        return response()->json(['success' => true, 'message' => 'المحتوى المنشور.', ...$reader->listing($type, (int) ($filters['page'] ?? 1), (int) ($filters['per_page'] ?? 20))])->header('Cache-Control', 'no-store');
    }

    public function item(Request $request, PublishedStoreContent $reader, string $type, string $slug)
    {
        abort_if($request->query() !== [], 422);

        return response()->json(['success' => true, 'message' => 'الوثيقة المنشورة.', 'data' => $reader->item($type, $slug)])->header('Cache-Control', 'no-store');
    }
}
