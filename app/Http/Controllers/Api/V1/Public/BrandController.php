<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\BrandResource;
use App\Services\Catalog\BrandService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection(
            $this->brandService->listActive()
        );
    }

    public function show(string $slug): BrandResource
    {
        return BrandResource::make(
            $this->brandService->findBySlug($slug)
        );
    }
}
