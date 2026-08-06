<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;
use App\Support\CarModelPricePayload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CarModelService
{
    public function listByBrand(Brand $brand, ?int $year = null, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = min(
            $perPage ?? (int) config('public_api.per_page', 20),
            (int) config('public_api.max_per_page', 100)
        );

        $query = $brand->carModels()->active()->orderBy('name');

        if ($year === null) {
            $query->with(array('cheapestOfficialOffer', 'representativeMarketStatistic'));
        }

        $models = $query->paginate($perPage);

        if ($year !== null) {
            $this->attachYearPricePayload($models->getCollection(), $year);
        }

        return $models;
    }

    private function attachYearPricePayload(Collection $models, int $year): void
    {
        $modelIds = $models->pluck('id')->all();

        if (empty($modelIds)) {
            return;
        }

        $offers = OfficialOffer::query()
            ->published()
            ->whereIn('model_id', $modelIds)
            ->where('year', $year)
            ->orderBy('price_amount')
            ->get()
            ->groupBy('model_id')
            ->map(fn ($group) => $group->first());

        $stats = MarketPriceStatistic::query()
            ->whereIn('model_id', $modelIds)
            ->where('year', $year)
            ->whereNull('region_code')
            ->get()
            ->keyBy('model_id');

        foreach ($models as $model) {
            $model->setAttribute('pricePayload', new CarModelPricePayload(
                $offers->get($model->id),
                $stats->get($model->id),
            ));
        }
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
