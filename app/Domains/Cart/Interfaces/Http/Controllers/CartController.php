<?php

namespace App\Domains\Cart\Interfaces\Http\Controllers;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Application\CartSnapshot;
use App\Domains\Cart\Interfaces\Http\Resources\CartItemResource;
use App\Http\Requests\PersonalCollectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class CartController
{
    public function __construct(private readonly CartOperations $operations, private readonly CartSnapshot $snapshot) {}

    public function show(PersonalCollectionRequest $request): JsonResponse
    {
        $input = $request->validated();
        $page = (int) ($input['page'] ?? 1);
        $perPage = (int) ($input['per_page'] ?? 12);
        $data = $this->snapshot->read($request->user('customer')->getAuthIdentifier(), $page, $perPage);
        $data['items'] = array_map(fn (array $item) => (new CartItemResource($item))->resolve($request), $data['items']);
        $paginator = new LengthAwarePaginator($data['items'], $data['item_count'], $perPage, $page, ['path' => $request->url(), 'query' => $input]);

        return response()->json(['success' => true, 'message' => 'محتوى السلة.', 'data' => $data,
            'links' => ['first' => $paginator->url(1), 'last' => $paginator->url($paginator->lastPage()), 'prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
            'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $data['item_count'], 'last_page' => $paginator->lastPage()]]);
    }

    private function result(PersonalCollectionRequest $request, array $data, int $status = 200): JsonResponse
    {
        if (isset($data['item'])) {
            $data['item'] = (new CartItemResource($data['item']))->resolve($request);
        }
        unset($data['created']);

        return response()->json(['success' => true, 'message' => 'تم تحديث السلة.', 'data' => $data], $status);
    }

    public function add(PersonalCollectionRequest $request): JsonResponse
    {
        $input = $request->validated();
        $data = $this->operations->add($request->user('customer')->getAuthIdentifier(), $input['product_id'], $input['variant_id'] ?? null, $input['quantity']);

        return $this->result($request, $data, $data['created'] ? 201 : 200);
    }

    public function update(PersonalCollectionRequest $request, string $itemId): JsonResponse
    {
        validator(['item_id' => $itemId], ['item_id' => 'required|uuid'])->validate();

        return $this->result($request, $this->operations->update($request->user('customer')->getAuthIdentifier(), $itemId, $request->validated()));
    }

    public function remove(PersonalCollectionRequest $request, string $itemId): JsonResponse
    {
        validator(['item_id' => $itemId], ['item_id' => 'required|uuid'])->validate();

        return $this->result($request, $this->operations->remove($request->user('customer')->getAuthIdentifier(), $itemId));
    }
}
