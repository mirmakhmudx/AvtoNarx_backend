<?php

use App\Enums\EntityType;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\DiscoveredBrand;
use App\Models\ParserTarget;
use App\Models\Source;
use App\Models\UnmatchedBrandModelCandidate;
use App\Services\Catalog\CatalogAliasService;
use App\Services\Parser\ParserTargetDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->source = Source::create(array(
        'code' => 'olx_uz',
        'name' => 'OLX.uz',
        'type' => 'marketplace',
        'base_url' => 'https://www.olx.uz',
        'is_active' => true,
        'ingestion_enabled' => true,
        'trust_level' => 'unverified',
        'settings' => array(),
    ));

    $this->brand = Brand::create(array('name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1));
    $this->model = CarModel::create(array('brand_id' => $this->brand->id, 'name' => 'Cobalt', 'slug' => 'cobalt', 'is_active' => true));

    $this->service = app(ParserTargetDiscoveryService::class);
});

it('creates a parser target directly when brand/model already resolve via verified aliases', function () {
    $aliasService = app(CatalogAliasService::class);

    $brandAlias = $aliasService->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);
    $aliasService->verify($brandAlias);

    $modelAlias = $aliasService->createPendingAlias(EntityType::Model, $this->model->id, 'Cobalt', $this->source->id);
    $aliasService->verify($modelAlias);

    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Chevrolet', 'model_name' => 'Cobalt', 'url' => 'https://www.olx.uz/x/cobalt/'),
    ));

    expect($result['matched'])->toBe(1);
    expect($result['auto_created'])->toBe(0);
    expect($result['unmatched'])->toBe(0);
    expect(ParserTarget::count())->toBe(1);

    $target = ParserTarget::first();
    expect($target->brand_id)->toBe($this->brand->id);
    expect($target->model_id)->toBe($this->model->id);
    expect($target->is_active)->toBeTrue();
});

it('auto-creates brand and model when the brand passed discovery but is not yet in the catalog', function () {
    DiscoveredBrand::create(array(
        'source_id' => $this->source->id,
        'name' => 'Toyota',
        'slug' => 'toyota',
        'discovered_url' => 'https://www.olx.uz/transport/legkovye-avtomobili/toyota/',
    ));

    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Toyota', 'model_name' => 'Camry', 'url' => 'https://www.olx.uz/x/toyota/camry/'),
    ));

    expect($result['matched'])->toBe(0);
    expect($result['auto_created'])->toBe(1);
    expect($result['unmatched'])->toBe(0);

    $brand = Brand::where('slug', 'toyota')->first();
    expect($brand)->not->toBeNull();

    $model = CarModel::where('brand_id', $brand->id)->where('name', 'Camry')->first();
    expect($model)->not->toBeNull();

    expect(ParserTarget::where('brand_id', $brand->id)->where('model_id', $model->id)->exists())->toBeTrue();
});

it('falls back to the pending queue when the brand has not passed the discovery quality filter', function () {
    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'SomeUnknownBrand', 'model_name' => 'X1', 'url' => 'https://www.olx.uz/x/unknown/x1/'),
    ));

    expect($result['matched'])->toBe(0);
    expect($result['auto_created'])->toBe(0);
    expect($result['unmatched'])->toBe(1);

    expect(
        UnmatchedBrandModelCandidate::where('brand_name_raw', 'SomeUnknownBrand')
            ->where('status', 'pending')
            ->exists()
    )->toBeTrue();
});

it('merges Cyrillic/Latin visual duplicate model names into a single model instead of creating a new one', function () {
    DiscoveredBrand::create(array(
        'source_id' => $this->source->id,
        'name' => 'Audi',
        'slug' => 'audi',
        'discovered_url' => 'https://www.olx.uz/transport/legkovye-avtomobili/audi/',
    ));

    // Avval lotincha "A5" bilan.
    $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Audi', 'model_name' => 'A5', 'url' => 'https://www.olx.uz/x/audi/a5/'),
    ));

    $brand = Brand::where('slug', 'audi')->first();
    expect(CarModel::where('brand_id', $brand->id)->count())->toBe(1);

    // Endi kirillcha "А5" — ko'rinishi bir xil, lekin boshqa Unicode belgi.
    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Audi', 'model_name' => 'А5', 'url' => 'https://www.olx.uz/x/audi/a5-cyrillic/'),
    ));

    // Yangi CarModel yaratilmasligi kerak — mavjudiga alias sifatida qo'shiladi.
    expect(CarModel::where('brand_id', $brand->id)->count())->toBe(1);
    expect($result['auto_created'])->toBe(1);
    expect($result['unmatched'])->toBe(0);
});

it('rejects junk (too short) model names and leaves them in the pending queue', function () {
    DiscoveredBrand::create(array(
        'source_id' => $this->source->id,
        'name' => 'Chevrolet',
        'slug' => 'chevrolet',
        'discovered_url' => 'https://www.olx.uz/transport/legkovye-avtomobili/chevrolet/',
    ));

    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Chevrolet', 'model_name' => 'G', 'url' => 'https://www.olx.uz/x/chevrolet/g/'),
    ));

    expect($result['auto_created'])->toBe(0);
    expect($result['unmatched'])->toBe(1);

    expect(
        UnmatchedBrandModelCandidate::where('model_name_raw', 'G')
            ->where('status', 'pending')
            ->exists()
    )->toBeTrue();
});

it('accepts purely numeric model codes (e.g. UAZ-style) instead of rejecting them as junk', function () {
    DiscoveredBrand::create(array(
        'source_id' => $this->source->id,
        'name' => 'УАЗ',
        'slug' => 'uaz',
        'discovered_url' => 'https://www.olx.uz/transport/legkovye-avtomobili/uaz/',
    ));

    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'УАЗ', 'model_name' => '31512-010', 'url' => 'https://www.olx.uz/x/uaz/31512-010/'),
    ));

    expect($result['auto_created'])->toBe(1);
    expect($result['unmatched'])->toBe(0);

    $brand = Brand::where('slug', 'uaz')->first();
    expect(CarModel::where('brand_id', $brand->id)->where('name', '31512-010')->exists())->toBeTrue();
});

it('reprocessPendingCandidates resolves previously pending candidates once auto-creation applies', function () {
    DiscoveredBrand::create(array(
        'source_id' => $this->source->id,
        'name' => 'Nissan',
        'slug' => 'nissan',
        'discovered_url' => 'https://www.olx.uz/transport/legkovye-avtomobili/nissan/',
    ));

    UnmatchedBrandModelCandidate::create(array(
        'source_id' => $this->source->id,
        'brand_name_raw' => 'Nissan',
        'model_name_raw' => 'Qashqai',
        'discovered_url' => 'https://www.olx.uz/x/nissan/qashqai/',
        'status' => 'pending',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ));

    $result = $this->service->reprocessPendingCandidates();

    expect($result['auto_created'])->toBe(1);
    expect($result['still_pending'])->toBe(0);

    $brand = Brand::where('slug', 'nissan')->first();
    expect($brand)->not->toBeNull();
    expect(CarModel::where('brand_id', $brand->id)->where('name', 'Qashqai')->exists())->toBeTrue();

    $candidate = UnmatchedBrandModelCandidate::where('model_name_raw', 'Qashqai')->first();
    expect($candidate->status)->toBe('resolved');
});

it('reprocessPendingCandidates leaves genuinely junky candidates untouched', function () {
    UnmatchedBrandModelCandidate::create(array(
        'source_id' => $this->source->id,
        'brand_name_raw' => 'Mitsubishi',
        'model_name_raw' => 'i',
        'discovered_url' => 'https://www.olx.uz/x/mitsubishi/i/',
        'status' => 'pending',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ));

    $result = $this->service->reprocessPendingCandidates();

    expect($result['auto_created'])->toBe(0);
    expect($result['still_pending'])->toBe(1);

    $candidate = UnmatchedBrandModelCandidate::where('model_name_raw', 'i')->first();
    expect($candidate->status)->toBe('pending');
});
