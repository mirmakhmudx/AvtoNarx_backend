<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->source = Source::create([
        'code' => 'uzum_avto',
        'name' => 'Uzum Avto',
        'type' => 'manufacturer',
        'base_url' => 'https://avto.uzum.uz',
        'is_active' => true,
        'ingestion_enabled' => true,
        'trust_level' => 'official',
        'settings' => [],
    ]);

    $this->brand = Brand::create(['name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1]);
    $this->model = CarModel::create(['brand_id' => $this->brand->id, 'name' => 'Cobalt', 'slug' => 'cobalt', 'is_active' => true]);
});

function makeOfficialOffer(array $overrides = []): OfficialOffer
{
    static $counter = 0;
    $counter++;

    return OfficialOffer::create(array_merge([
        'source_id' => test()->source->id,
        'brand_id' => test()->brand->id,
        'model_id' => test()->model->id,
        'external_id' => 'offer-'.$counter,
        'price_amount' => 145_000_000,
        'currency' => 'UZS',
        'price_uzs' => 145_000_000,
        'source_url' => 'https://avto.uzum.uz/cobalt',
        'publication_status' => 'published',
        'observed_at' => now(),
        'content_hash' => bin2hex(random_bytes(16)),
    ], $overrides));
}

function makeStat(array $overrides = []): MarketPriceStatistic
{
    return MarketPriceStatistic::create(array_merge([
        'brand_id' => test()->brand->id,
        'model_id' => test()->model->id,
        'year' => 2026,
        'region_code' => null,
        'currency' => 'UZS',
        'sample_size' => 12,
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
    ], $overrides));
}

it('returns official_price and market_price in the TZ-specified shape on the model listing', function () {
    makeOfficialOffer();
    makeStat();

    $response = $this->getJson("/api/v1/brands/{$this->brand->slug}/models");

    $response->assertOk();
    $item = $response->json('data.0');

    expect($item['official_price'])->toHaveKeys(['amount', 'currency', 'observed_at', 'source_url']);
    expect($item['official_price']['amount'])->toBe(145_000_000);
    expect($item['official_price']['currency'])->toBe('UZS');

    expect($item['market_price'])->toHaveKeys(['amount', 'currency', 'statistic', 'sample_size', 'period_to', 'method_version']);
    expect($item['market_price']['amount'])->toBe(140_000_000);
    expect($item['market_price']['statistic'])->toBe('median');
    expect($item['market_price']['sample_size'])->toBe(12);
});

it('picks the statistic with the largest sample_size as the representative market_price when no year is given', function () {
    makeStat(['year' => 2024, 'sample_size' => 10, 'median_price_uzs' => 100_000_000]);
    makeStat(['year' => 2026, 'sample_size' => 30, 'median_price_uzs' => 150_000_000]);

    $response = $this->getJson("/api/v1/brands/{$this->brand->slug}/models");

    $response->assertOk();
    expect($response->json('data.0.market_price.amount'))->toBe(150_000_000);
    expect($response->json('data.0.market_price.sample_size'))->toBe(30);
});

it('filters official_price/market_price by year and returns null when nothing matches that year', function () {
    makeOfficialOffer(['year' => 2026, 'price_amount' => 145_000_000, 'price_uzs' => 145_000_000]);
    makeStat(['year' => 2026, 'median_price_uzs' => 140_000_000]);

    $matchingYear = $this->getJson("/api/v1/brands/{$this->brand->slug}/models?year=2026");
    $matchingYear->assertOk();
    expect($matchingYear->json('data.0.official_price.amount'))->toBe(145_000_000);
    expect($matchingYear->json('data.0.market_price.amount'))->toBe(140_000_000);

    $otherYear = $this->getJson("/api/v1/brands/{$this->brand->slug}/models?year=2020");
    $otherYear->assertOk();
    expect($otherYear->json('data.0.official_price'))->toBeNull();
    expect($otherYear->json('data.0.market_price'))->toBeNull();
});

it('paginates the model listing with a single, consistent pagination shape', function () {
    for ($i = 0; $i < 3; $i++) {
        CarModel::create([
            'brand_id' => $this->brand->id,
            'name' => 'Model '.$i,
            'slug' => 'model-'.$i,
            'is_active' => true,
        ]);
    }

    $response = $this->getJson("/api/v1/brands/{$this->brand->slug}/models?page=1");

    $response->assertOk();
    expect($response->json('meta'))->toHaveKeys(['current_page', 'per_page', 'total', 'last_page']);
    expect($response->json('meta.total'))->toBe(4);
    expect($response->json('meta.current_page'))->toBe(1);
});

it('returns ETag and Last-Modified headers and responds 304 when If-None-Match matches', function () {
    $first = $this->getJson("/api/v1/brands/{$this->brand->slug}/models");

    $first->assertOk();
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();
    expect($first->headers->get('Last-Modified'))->not->toBeNull();

    $second = $this->withHeaders(['If-None-Match' => $etag])
        ->getJson("/api/v1/brands/{$this->brand->slug}/models");

    $second->assertStatus(304);
});
