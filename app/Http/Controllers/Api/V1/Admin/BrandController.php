<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Http\Resources\Catalog\BrandResource;
use App\Models\Brand;
use App\Services\Catalog\BrandService;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService,
    ) {}

    public function store(StoreBrandRequest $request): BrandResource
    {
        $this->authorize('create', Brand::class);

        $brand = $this->brandService->create($request->validated());

        return BrandResource::make($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        $this->authorize('update', $brand);

        $updated = $this->brandService->update($brand, $request->validated());

        return BrandResource::make($updated);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->authorize('delete', $brand);

        $this->brandService->deactivate($brand);

        return response()->json(['message' => 'Brand deactivated']);
    }
}
