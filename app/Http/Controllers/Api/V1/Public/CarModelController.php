<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\CarModelResource;
use App\Services\Catalog\BrandService;
use App\Services\Catalog\CarModelService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarModelController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
        private readonly CarModelService $carModelService,
    ) {
    }

    public function index(string $brandSlug): AnonymousResourceCollection
    {
        $brand = $this->brandService->findBySlug($brandSlug);

        return CarModelResource::collection(
            $this->carModelService->listByBrand($brand)
        );
    }
}
