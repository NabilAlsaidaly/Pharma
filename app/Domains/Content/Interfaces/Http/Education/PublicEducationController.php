<?php

namespace App\Domains\Content\Interfaces\Http\Education;

use App\Domains\Content\Domain\Education\ArticleDocument;
use App\Domains\Content\Infrastructure\Education\EducationalStore;
use App\Domains\Content\Infrastructure\Education\PublishedEducation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PublicEducationController
{
    public static function filters(Request $request, bool $articles = true): array
    {
        ArticleDocument::only($request->query(), $articles ? ['page', 'per_page', 'category', 'concern', 'featured', 'q'] : ['page', 'per_page']);
        $data = $request->validate(['page' => 'sometimes|integer|between:1,1000', 'per_page' => 'sometimes|integer|between:1,48', 'category' => 'sometimes|string|max:80|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'concern' => 'sometimes|string|max:255|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'featured' => 'sometimes|in:0,1', 'q' => 'sometimes|nullable|string|max:100']);
        if (isset($data['q'])) {
            $q = trim(ArticleDocument::text($data['q'], 100));
            if ($q === '') {
                unset($data['q']);
            } else {
                abort_if(mb_strlen($q) < 2, 422);
                $data['q'] = $q;
            }
        }

        return $data;
    }

    public function index(Request $request, PublishedEducation $reader, ?string $slug = null)
    {
        $filters = self::filters($request);
        abort_if($slug !== null && isset($filters['category']), 422);
        if ($slug !== null) {
            $filters['category'] = $slug;
        }

        return JsonResource::collection($reader->listing($filters))->additional(['success' => true, 'message' => 'المقالات والمعلومات المنشورة.']);
    }

    public function show(Request $request, PublishedEducation $reader, string $slug)
    {
        abort_if($request->query() !== [], 422);

        return response()->json(['success' => true, 'message' => 'المقال المنشور.', 'data' => $reader->item($slug)]);
    }

    public function categories(Request $request)
    {
        EducationalStore::ready();
        $f = self::filters($request, false);
        $rows = DB::table('educational_categories')->whereNull('archived_at')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->paginate($f['per_page'] ?? 12, ['id', 'slug', 'name', 'description', 'sort_order'], 'page', $f['page'] ?? 1);
        $rows->setCollection($rows->getCollection()->map(fn ($row) => (array) $row));

        return JsonResource::collection($rows)->additional(['success' => true, 'message' => 'تصنيفات المقالات المتاحة.']);
    }

    public function media(Request $request, PublishedEducation $reader, string $id)
    {
        abort_if($request->query() !== [], 422);
        $reader->media($id);

        return self::image($id);
    }

    public static function image(string $id)
    {
        abort_unless(Str::isUuid($id) && Storage::disk('local')->exists('education/'.$id.'.png'), 404);

        return response()->file(Storage::disk('local')->path('education/'.$id.'.png'), ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
