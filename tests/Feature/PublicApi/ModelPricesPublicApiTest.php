<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->source = Source::create(array(
        'code' => 'uzum_avto',
        'name' => 'Uzum Avto',
        'type' => 'manufacturer',
        'base_url' => 'https://avto.uzum.uz',
        'is_active' => true,
        'ingestion_enabled' => true,
        'trust_level' => 'official',
        'settings' => array(),
    ));

    $this->brand = Brand::create(array('name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1));
    $this->model = CarModel::create(array('brand_id' => $this->brand->id, 'name' => 'Cobalt', 'slug' => 'cobalt', 'is_active' => true));
});

function makeRegionStat(array $overrides = array()): MarketPriceStatistic
{
    return MarketPriceStatistic::create(array_merge(array(
        'brand_id' => test()->brand->id,
        'model_id' => test()->model->id,
        'year' => 2026,
        'region_code' => null,
        'currency' => 'UZS',
        'sample_size' => 15,
        'excluded_count' => 0,
        'median_price_uzs' => 140_000_000,
        'mean_price_uzs' => 141_000_000,
        'min_price_uzs' => 120_000_000,
        'max_price_uzs' => 160_000_000,
        'p25_price_uzs' => 130_000_000,
        'p75_price_uzs' => 150_000_000,
        'period_from' => now()->subMonth(),
        'period_to' => now(),
        'method_version' => '1.0',
        'calculated_at' => now(),
    ), $overrides));
}

it('returns the nationwide (region_code = null) statistic when no region is given', function () {
    makeRegionStat(array('region_code' => null, 'median_price_uzs' => 140_000_000));
    makeRegionStat(array('region_code' => 'TAS', 'median_price_uzs' => 160_000_000));

    $response = $this->getJson("/api/v1/models/{$this->model->id}/prices");

    $response->assertOk();
    $entry = collect($response->json('market_prices'))->firstWhere('year', 2026);
    expect($entry['median_uzs'])->toBe(140_000_000);
});

it('filters market_prices by region when ?region= is given', function () {
    makeRegionStat(array('region_code' => null, 'median_price_uzs' => 140_000_000));
    makeRegionStat(array('region_code' => 'TAS', 'median_price_uzs' => 160_000_000));

    $response = $this->getJson("/api/v1/models/{$this->model->id}/prices?region=TAS");

    $response->assertOk();
    expect($response->json('region'))->toBe('TAS');
    $entry = collect($response->json('market_prices'))->firstWhere('year', 2026);
    expect($entry['median_uzs'])->toBe(160_000_000);
});

it('returns official_price in the TZ shape (amount/currency/observed_at/source_url)', function () {
    OfficialOffer::create(array(
        'source_id' => $this->source->id,
        'brand_id' => $this->brand->id,
        'model_id' => $this->model->id,
        'external_id' => 'offer-1',
        'price_amount' => 145_000_000,
        'currency' => 'UZS',
        'price_uzs' => 145_000_000,
        'source_url' => 'https://avto.uzum.uz/cobalt',
        'publication_status' => 'published',
        'observed_at' => now(),
        'content_hash' => bin2hex(random_bytes(16)),
    ));

    $response = $this->getJson("/api/v1/models/{$this->model->id}/prices");

    $response->assertOk();
    expect($response->json('official_price'))->toHaveKeys(array('amount', 'currency', 'observed_at', 'source_url'));
    expect($response->json('official_price.amount'))->toBe(145_000_000);
});

it('reports insufficient_sample / no_data status with min_required for years without a statistic', function () {
    $response = $this->getJson("/api/v1/models/{$this->model->id}/prices?year=2030");

    $response->assertOk();
    $entry = $response->json('market_prices.0');
    expect($entry['year'])->toBe(2030);
    expect($entry['status'])->toBe('no_data');
    expect($entry['min_required'])->toBe(10);
});
