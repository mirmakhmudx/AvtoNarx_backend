<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\CarModelResource;
use App\Services\Catalog\BrandService;
use App\Services\Catalog\CarModelService;
use App\Services\PublicApi\ApiCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarModelController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
        private readonly CarModelService $carModelService,
        private readonly ApiCacheService $apiCache,
    ) {
    }

    public function index(Request $request, string $brandSlug): JsonResponse
    {
        $brand = $this->brandService->findBySlug($brandSlug);

        $year = $request->query('year') !== null ? (int) $request->query('year') : null;
        $page = max(1, (int) $request->query('page', 1));

        $cacheKey = sprintf(
            'public:v1:brands:%s:models:year:%s:page:%d',
            $brand->slug,
            $year ?? 'all',
            $page
        );

        return $this->apiCache->respond($cacheKey, $request, function () use ($brand, $year) {
            $models = $this->carModelService->listByBrand($brand, $year);

            return array(
                'data' => CarModelResource::collection($models->items())->resolve(request()),
                'meta' => array(
                    'current_page' => $models->currentPage(),
                    'per_page' => $models->perPage(),
                    'total' => $models->total(),
                    'last_page' => $models->lastPage(),
                ),
            );
        });
    }
}
