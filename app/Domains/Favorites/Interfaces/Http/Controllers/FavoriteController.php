<?php

namespace App\Domains\Favorites\Interfaces\Http\Controllers;

use App\Domains\Catalog\Interfaces\Http\Resources\ProductResource;
use App\Domains\Favorites\Application\FavoriteOperations;
use App\Http\Requests\PersonalCollectionRequest;
use Illuminate\Http\JsonResponse;

class FavoriteController
{
    public function __construct(private readonly FavoriteOperations $operations) {}

    private function response(array $data, int $status = 200): JsonResponse
    {
        unset($data['created']);

        return response()->json(['success' => true, 'message' => 'المفضلة.', 'data' => $data], $status);
    }

    public function listing(PersonalCollectionRequest $request): JsonResponse
    {
        $input = $request->validated();
        $paginator = $this->operations->listing($request->user('customer')->getAuthIdentifier(), (int) ($input['per_page'] ?? 12));
        $response = ProductResource::collection($paginator)->withQuery($input)->toResponse($request)->getData(true);

        return response()->json(['success' => true, 'message' => 'المفضلة.', 'data' => $response['data'], 'links' => $response['links'], 'meta' => $response['meta']]);
    }

    public function check(PersonalCollectionRequest $request): JsonResponse
    {
        return $this->response($this->operations->check($request->user('customer')->getAuthIdentifier(), $request->validated('product_id')));
    }

    public function add(PersonalCollectionRequest $request): JsonResponse
    {
        $data = $this->operations->add($request->user('customer')->getAuthIdentifier(), $request->validated('product_id'));

        return $this->response($data, $data['created'] ? 201 : 200);
    }

    public function remove(PersonalCollectionRequest $request, string $productId): JsonResponse
    {
        validator(['product_id' => $productId], ['product_id' => 'required|uuid'])->validate();

        return $this->response($this->operations->remove($request->user('customer')->getAuthIdentifier(), $productId));
    }
}
