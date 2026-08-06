<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\BrandResource;
use App\Services\Catalog\BrandService;
use App\Services\PublicApi\ApiCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
        private readonly ApiCacheService $apiCache,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->apiCache->respond('public:v1:brands:index', $request, function () {
            return array(
                'data' => BrandResource::collection($this->brandService->listActive())->resolve(request()),
            );
        });
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $brand = $this->brandService->findBySlug($slug);

        return $this->apiCache->respond('public:v1:brands:show:' . $slug, $request, function () use ($brand) {
            return array(
                'data' => BrandResource::make($brand)->resolve(request()),
            );
        });
    }
}
