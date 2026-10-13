<?php

namespace App\Domains\Orders\Interfaces\Http\Controllers;

use App\Domains\Orders\Application\PreliminaryRequestHistory;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use App\Domains\Orders\Interfaces\Http\Requests\PreliminaryCheckoutRequest;
use App\Domains\Orders\Interfaces\Http\Requests\PreliminaryHistoryRequest;
use App\Domains\Orders\Interfaces\Http\Resources\PreliminaryRequestResource;
use Illuminate\Http\JsonResponse;

class PreliminaryRequestController
{
    public function store(PreliminaryCheckoutRequest $request, SubmitPreliminaryCheckoutRequest $submit): JsonResponse
    {
        $result = $submit->execute($request->user('customer')->getAuthIdentifier(), $request->validated());

        return response()->json(['success' => true, 'message' => 'تم تسجيل طلبك المبدئي للمراجعة.',
            'data' => (new PreliminaryRequestResource($result['intent']))->resolve($request)], $result['created'] ? 201 : 200);
    }

    public function index(PreliminaryHistoryRequest $request, PreliminaryRequestHistory $history): JsonResponse
    {
        $input = $request->validated();
        $page = $history->listing($request->user('customer')->getAuthIdentifier(), (int) ($input['page'] ?? 1), (int) ($input['per_page'] ?? 12));
        $page->appends($input);

        return response()->json(['success' => true, 'message' => 'طلباتك المبدئية.',
            'data' => array_map(fn ($intent) => (new PreliminaryRequestResource($intent))->resolve($request), $page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
            'links' => ['first' => $page->url(1), 'last' => $page->url($page->lastPage()), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()]]);
    }

    public function show(PreliminaryHistoryRequest $request, PreliminaryRequestHistory $history, string $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'تفاصيل الطلب المبدئي.',
            'data' => (new PreliminaryRequestResource($history->detail($request->user('customer')->getAuthIdentifier(), $id)))->resolve($request)]);
    }
}
