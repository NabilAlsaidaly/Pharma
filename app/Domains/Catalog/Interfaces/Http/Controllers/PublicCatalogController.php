<?php

namespace App\Domains\Catalog\Interfaces\Http\Controllers;

use App\Domains\Catalog\Application\Ports\PublicCatalogReader;
use App\Domains\Catalog\Interfaces\Http\Requests\CatalogListRequest;
use App\Domains\Catalog\Interfaces\Http\Resources\BrandResource;
use App\Domains\Catalog\Interfaces\Http\Resources\ConcernResource;
use App\Domains\Catalog\Interfaces\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicCatalogController
{
    public function __construct(private readonly PublicCatalogReader $catalog) {}

    public function brands(CatalogListRequest $request): JsonResponse
    {
        return $this->collection(BrandResource::collection($this->catalog->brands($request->filters()->perPage)), $request);
    }

    public function brand(Request $request, string $slug): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'بيانات العلامة التجارية.', 'data' => (new BrandResource($this->catalog->brand($slug)))->resolve($request)]);
    }

    public function concerns(CatalogListRequest $request): JsonResponse
    {
        return $this->collection(ConcernResource::collection($this->catalog->concerns($request->filters()->perPage)), $request);
    }

    public function products(CatalogListRequest $request): JsonResponse
    {
        return $this->collection(ProductResource::collection($this->catalog->products($request->filters())), $request);
    }

    public function recommended(CatalogListRequest $request): JsonResponse
    {
        return $this->collection(ProductResource::collection($this->catalog->products($request->filters(), recommendedOnly: true)), $request);
    }

    public function brandProducts(CatalogListRequest $request, string $slug): JsonResponse
    {
        return $this->collection(ProductResource::collection($this->catalog->products($request->filters(), brandId: $this->catalog->brand($slug)->id)), $request);
    }

    public function concernProducts(CatalogListRequest $request, string $slug): JsonResponse
    {
        return $this->collection(ProductResource::collection($this->catalog->products($request->filters(), concernId: $this->catalog->concern($slug)->id)), $request);
    }

    public function product(Request $request, string $slug): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'بيانات المنتج.', 'data' => (new ProductResource($this->catalog->product($slug)))->resolve($request)]);
    }

    private function collection(AnonymousResourceCollection $resource, CatalogListRequest $request): JsonResponse
    {
        $response = $resource->withQuery($request->validated())->toResponse($request);
        $response->setData(['success' => true, 'message' => 'بيانات الكتالوج.', ...$response->getData(true)]);

        return $response;
    }
}
