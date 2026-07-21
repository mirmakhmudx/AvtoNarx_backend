<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Database\Eloquent\Collection;

class CarModelService
{
    public function listByBrand(Brand $brand): Collection
    {
        return $brand->carModels()
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): CarModel
    {
        return CarModel::create($data);
    }

    public function update(CarModel $carModel, array $data): CarModel
    {
        $carModel->update($data);

        return $carModel->refresh();
    }
}
