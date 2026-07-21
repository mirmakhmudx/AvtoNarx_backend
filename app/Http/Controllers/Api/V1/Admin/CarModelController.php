<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCarModelRequest;
use App\Http\Requests\Catalog\UpdateCarModelRequest;
use App\Http\Resources\Catalog\CarModelResource;
use App\Models\CarModel;
use App\Services\Catalog\CarModelService;

class CarModelController extends Controller
{
    public function __construct(
        private readonly CarModelService $carModelService,
    ) {
    }

    public function store(StoreCarModelRequest $request): CarModelResource
    {
        return CarModelResource::make(
            $this->carModelService->create($request->validated())
        );
    }

    public function update(UpdateCarModelRequest $request, CarModel $carModel): CarModelResource
    {
        return CarModelResource::make(
            $this->carModelService->update($carModel, $request->validated())
        );
    }
}
