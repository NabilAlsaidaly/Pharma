<?php

namespace App\Domains\Delivery\Interfaces\Http;

use App\Domains\Settings\Infrastructure\PublishedDeliveryZones;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicDeliveryController
{
    public function __invoke(Request $request, PublishedDeliveryZones $zones)
    {
        abort_if(array_diff(array_keys($request->query()), ['page', 'per_page']) !== [], 422);
        $filters = validator($request->query(), ['page' => 'sometimes|integer|between:1,1000', 'per_page' => 'sometimes|integer|between:1,48'])->validate();

        return JsonResource::collection($zones->listing($filters))->additional(['success' => true, 'message' => 'مناطق منشورة للعرض الإرشادي فقط؛ التغطية والرسوم تحتاجان تأكيداً يدوياً. الرسوم الفارغة غير محددة وليست مجانية.']);
    }
}
