<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    public function listActive(): Collection
    {
        return Brand::query()
            ->active()
            ->withCount('carModels')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findBySlug(string $slug): Brand
    {
        return Brand::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function create(array $data): Brand
    {
        return Brand::create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand->refresh();
    }

    public function deactivate(Brand $brand): Brand
    {
        $brand->update(['is_active' => false]);

        return $brand;
    }
}
