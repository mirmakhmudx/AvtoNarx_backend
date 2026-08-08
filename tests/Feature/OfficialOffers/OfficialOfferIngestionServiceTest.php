<?php

use App\DTO\OfficialOfferData;
use App\Enums\EntityType;
use App\Exceptions\UnmatchedCatalogEntityException;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\OfficialOffer;
use App\Models\Source;
use App\Services\Catalog\CatalogAliasService;
use App\Services\ExchangeRates\ExchangeRateService;
use App\Services\OfficialOffers\OfficialOfferIngestionService;
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

    $this->aliasService = app(CatalogAliasService::class);
    $this->service = app(OfficialOfferIngestionService::class);
});

function verifyOfferAliases(): void
{
    $brandAlias = test()->aliasService->createPendingAlias(EntityType::Brand, test()->brand->id, 'Chevrolet', test()->source->id);
    test()->aliasService->verify($brandAlias);

    $modelAlias = test()->aliasService->createPendingAlias(EntityType::Model, test()->model->id, 'Cobalt', test()->source->id);
    test()->aliasService->verify($modelAlias);
}

function makeOfferData(array $overrides = []): OfficialOfferData
{
    return OfficialOfferData::fromArray(array_merge([
        'source_id' => test()->source->id,
        'external_id' => 'chevrolet-cobalt-style-at',
        'url' => 'https://avto.uzum.uz/cars/cobalt',
        'brand' => 'Chevrolet',
        'model' => 'Cobalt',
        'trim' => 'Style AT',
        'year' => 2026,
        'price' => ['amount' => 145000000, 'currency' => 'UZS'],
        'observed_at' => now()->toIso8601String(),
        'content_hash' => hash('sha256', 'default-fixture'),
    ], $overrides));
}

it('throws unmatched_brand when the brand cannot be resolved via alias', function () {
    // Aliaslar sozlanmagan — hech narsa resolve bo'lmaydi.
    try {
        $this->service->ingest(makeOfferData());
        $this->fail('UnmatchedCatalogEntityException kutilgan edi.');
    } catch (UnmatchedCatalogEntityException $e) {
        expect($e->errorCode())->toBe('unmatched_brand');
    }

    expect(OfficialOffer::count())->toBe(0);
});

it('throws unmatched_model when the brand resolves but the model does not', function () {
    $brandAlias = $this->aliasService->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);
    $this->aliasService->verify($brandAlias);

    try {
        $this->service->ingest(makeOfferData());
        $this->fail('UnmatchedCatalogEntityException kutilgan edi.');
    } catch (UnmatchedCatalogEntityException $e) {
        expect($e->errorCode())->toBe('unmatched_model');
    }

    expect(OfficialOffer::count())->toBe(0);
});

it('creates a new offer with pending status when brand and model both resolve', function () {
    verifyOfferAliases();

    $offer = $this->service->ingest(makeOfferData());

    expect(OfficialOffer::count())->toBe(1);
    expect($offer->publication_status->value)->toBe('pending');
    expect($offer->brand_id)->toBe($this->brand->id);
    expect($offer->model_id)->toBe($this->model->id);
    expect($offer->price_uzs)->toBe(145000000);
});

it('computes price_uzs via the exchange rate when the item is in USD', function () {
    verifyOfferAliases();
    app(ExchangeRateService::class)->setRate('USD', 'UZS', 12700, now()->toDateString());

    $offer = $this->service->ingest(makeOfferData([
        'price' => ['amount' => 12000, 'currency' => 'USD'],
    ]));

    expect($offer->price_uzs)->toBe(12000 * 12700);
});

it('does not reopen moderation when content_hash is unchanged on re-ingest', function () {
    verifyOfferAliases();

    $offer = $this->service->ingest(makeOfferData(['content_hash' => 'hash-a']));
    $offer->update(['publication_status' => 'published']);

    $again = $this->service->ingest(makeOfferData([
        'content_hash' => 'hash-a',
        'observed_at' => now()->addHour()->toIso8601String(),
    ]));

    expect(OfficialOffer::count())->toBe(1);
    expect($again->publication_status->value)->toBe('published');
    expect($again->id)->toBe($offer->id);
});

it('sets status back to pending when price/content changes, even if previously published', function () {
    verifyOfferAliases();

    $offer = $this->service->ingest(makeOfferData(['content_hash' => 'hash-a', 'price' => ['amount' => 145000000, 'currency' => 'UZS']]));
    $offer->update(['publication_status' => 'published']);

    $changed = $this->service->ingest(makeOfferData([
        'content_hash' => 'hash-b',
        'price' => ['amount' => 150000000, 'currency' => 'UZS'],
    ]));

    expect(OfficialOffer::count())->toBe(1);
    expect($changed->publication_status->value)->toBe('pending');
    expect($changed->price_amount)->toBe(150000000);
});

it('auto-publishes immediately when the source has settings->auto_publish = true', function () {
    verifyOfferAliases();
    $this->source->update(['settings' => ['auto_publish' => true]]);

    $offer = $this->service->ingest(makeOfferData());

    expect($offer->publication_status->value)->toBe('published');
    expect($offer->published_at)->not->toBeNull();
    expect($offer->verified_at)->not->toBeNull();
    expect($offer->verified_by)->toBeNull(); // tizim tomonidan, odam emas
});

it('does NOT auto-publish when the source has no auto_publish flag (defaults to pending)', function () {
    verifyOfferAliases();

    $offer = $this->service->ingest(makeOfferData());

    expect($offer->publication_status->value)->toBe('pending');
    expect($offer->published_at)->toBeNull();
});

it('treats different trim_name values as separate offers for the same model', function () {
    verifyOfferAliases();

    $this->service->ingest(makeOfferData([
        'external_id' => 'cobalt-style',
        'trim' => 'Style AT',
        'content_hash' => 'h1',
    ]));

    $this->service->ingest(makeOfferData([
        'external_id' => 'cobalt-lt',
        'trim' => 'LT MT',
        'content_hash' => 'h2',
    ]));

    expect(OfficialOffer::count())->toBe(2);
});
