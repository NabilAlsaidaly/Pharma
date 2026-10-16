<?php

namespace App\Domains\Gifts\Interfaces\Http;

use App\Domains\Gifts\Application\GiftInput;
use App\Domains\Gifts\Application\SubmitGiftRequest;
use App\Domains\Gifts\Infrastructure\GiftRequests;
use App\Domains\Gifts\Infrastructure\GiftStore;
use App\Domains\Gifts\Infrastructure\PublishedGifts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GiftApiController
{
    public function index(Request $request, PublishedGifts $reader)
    {
        GiftInput::only($request->query(), ['page', 'per_page', 'occasion_id', 'availability']);
        $filters = $request->validate(['page' => 'sometimes|integer|between:1,1000', 'per_page' => 'sometimes|integer|between:1,48', 'occasion_id' => 'sometimes|uuid', 'availability' => 'sometimes|in:available,on_request,unavailable']);

        return GiftResource::collection($reader->listing($filters))->additional(['success' => true, 'message' => 'باقات الهدايا المنشورة.']);
    }

    public function show(Request $request, PublishedGifts $reader, string $slug)
    {
        abort_if($request->query() !== [], 422);

        return (new GiftResource($reader->item($slug)))->additional(['success' => true, 'message' => 'باقة هدايا جاهزة؛ طلبها مبدئي.']);
    }

    public function occasions(Request $request)
    {
        GiftStore::ready();
        $filters = GiftRequests::filters($request->query());
        $rows = DB::table('gift_occasions')->whereNull('archived_at')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->paginate($filters['per_page'] ?? 25, ['id', 'name', 'description'], 'page', $filters['page'] ?? 1);

        return GiftResource::collection($rows)->additional(['success' => true, 'message' => 'المناسبات المتاحة.']);
    }

    public function media(Request $request, PublishedGifts $reader, string $id)
    {
        abort_if($request->query() !== [], 422);
        $reader->media($id);

        return self::image($id);
    }

    public static function image(string $id)
    {
        abort_unless(Str::isUuid($id) && Storage::disk('local')->exists('gifts/'.$id.'.png'), 404);

        return response()->file(Storage::disk('local')->path('gifts/'.$id.'.png'), ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    public function store(Request $request, SubmitGiftRequest $command, GiftRequests $reader)
    {
        $raw = json_decode($request->getContent());
        abort_unless($request->isJson() && is_object($raw) && is_object($raw->recipient ?? null) && $request->query() === [], 422);
        $input = json_decode($request->getContent(), true);
        $result = $command->execute($request->user('customer')->getAuthIdentifier(), $input);
        $data = $reader->detail($result['id'], $request->user('customer')->getAuthIdentifier());

        return response()->json(['success' => true, 'message' => 'طلب هدية مبدئي للمراجعة دون تأكيد شراء أو دفع أو شحن.', 'data' => $this->summary($data)], $result['created'] ? 201 : 200)->header('Cache-Control', 'no-store, private');
    }

    public function history(Request $request, GiftRequests $reader)
    {
        abort_unless(in_array(trim($request->getContent()), ['', '[]', '{}'], true), 422);
        $page = $reader->listing(GiftRequests::filters($request->query()), $request->user('customer')->getAuthIdentifier());
        $page->setCollection($page->getCollection()->map(fn ($r) => $this->summary((array) $r)));

        return GiftResource::collection($page)->additional(['success' => true, 'message' => 'طلبات الهدايا الخاصة بك.']);
    }

    public function requestDetail(Request $request, GiftRequests $reader, string $id)
    {
        abort_if($request->query() !== [] || ! in_array(trim($request->getContent()), ['', '[]', '{}'], true), 422);
        $data = $reader->detail($id, $request->user('customer')->getAuthIdentifier());

        return response()->json(['success' => true, 'message' => 'تفاصيل طلبك المبدئي.', 'data' => $data + $this->commercial()])->header('Cache-Control', 'no-store, private');
    }

    private function summary(array $row): array
    {
        $row['requires_fulfillment_review'] = (bool) $row['requires_fulfillment_review'];

        return array_intersect_key($row, array_flip(['id', 'status', 'package_name', 'currency', 'display_price_snapshot', 'requires_fulfillment_review', 'created_at'])) + $this->commercial();
    }

    private function commercial(): array
    {
        return ['requires_owner_review' => true, 'is_confirmed_order' => false, 'reserves_inventory' => false, 'payment_verified' => false, 'delivery_scheduled' => false, 'price_is_binding' => false, 'shipping_included' => false];
    }
}
