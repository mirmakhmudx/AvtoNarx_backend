<?php

use App\Enums\EntityType;
use App\Models\Brand;
use App\Models\CarModel;
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

    $this->aliasService = app(CatalogAliasService::class);
    $this->service = app(ParserTargetDiscoveryService::class);
});

it('creates a parser target directly when brand/model already resolve via verified aliases', function () {
    $brand = Brand::create(array('name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1));
    $model = CarModel::create(array('brand_id' => $brand->id, 'name' => 'Cobalt', 'slug' => 'cobalt', 'is_active' => true));

    $brandAlias = $this->aliasService->createPendingAlias(EntityType::Brand, $brand->id, 'Chevrolet', $this->source->id);
    $this->aliasService->verify($brandAlias);
    $modelAlias = $this->aliasService->createPendingAlias(EntityType::Model, $model->id, 'Cobalt', $this->source->id);
    $this->aliasService->verify($modelAlias);

    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Chevrolet', 'model_name' => 'Cobalt', 'url' => 'https://www.olx.uz/x/cobalt/'),
    ));

    expect($result['matched'])->toBe(1);
    expect($result['unmatched'])->toBe(0);
    expect($result['skipped_junk'])->toBe(0);
    expect(ParserTarget::count())->toBe(1);

    $target = ParserTarget::first();
    expect($target->brand_id)->toBe($brand->id);
    expect($target->model_id)->toBe($model->id);
    expect(CarModel::count())->toBe(1); // hech qanday yangi model yaratilmadi
});

it('never auto-creates a brand or model, even when the brand is well known — it goes to the pending queue instead', function () {
    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Toyota', 'model_name' => 'Camry', 'url' => 'https://www.olx.uz/x/toyota/camry/'),
    ));

    expect($result['matched'])->toBe(0);
    expect($result['unmatched'])->toBe(1);
    expect(Brand::count())->toBe(0); // TZ 10-bo'lim: avtomatik yaratish taqiqlangan
    expect(CarModel::count())->toBe(0);

    expect(
        UnmatchedBrandModelCandidate::where('brand_name_raw', 'Toyota')
            ->where('model_name_raw', 'Camry')
            ->where('status', 'pending')
            ->exists()
    )->toBeTrue();
});

it('rejects junk (region/pagination-like) names without adding them to the pending queue', function () {
    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Chevrolet', 'model_name' => 'область', 'url' => 'https://www.olx.uz/x/chevrolet/region/'),
    ));

    expect($result['skipped_junk'])->toBe(1);
    expect($result['unmatched'])->toBe(0);
    expect(UnmatchedBrandModelCandidate::count())->toBe(0);
});

it('rejects too-short model names as junk', function () {
    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Chevrolet', 'model_name' => 'G', 'url' => 'https://www.olx.uz/x/chevrolet/g/'),
    ));

    expect($result['skipped_junk'])->toBe(1);
    expect(UnmatchedBrandModelCandidate::count())->toBe(0);
});

it('accepts purely numeric model codes (e.g. UAZ-style) into the pending queue instead of rejecting them as junk', function () {
    $result = $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'УАЗ', 'model_name' => '31512-010', 'url' => 'https://www.olx.uz/x/uaz/31512-010/'),
    ));

    expect($result['unmatched'])->toBe(1);
    expect($result['skipped_junk'])->toBe(0);
    expect(UnmatchedBrandModelCandidate::where('model_name_raw', '31512-010')->exists())->toBeTrue();
});

it('sets first_seen_at only once and keeps updating last_seen_at on repeated discovery', function () {
    $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Toyota', 'model_name' => 'Camry', 'url' => 'https://www.olx.uz/x/toyota/camry/'),
    ));

    $first = UnmatchedBrandModelCandidate::where('model_name_raw', 'Camry')->first();
    $firstSeenAt = $first->first_seen_at;

    $this->travel(1)->days();

    $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Toyota', 'model_name' => 'Camry', 'url' => 'https://www.olx.uz/x/toyota/camry/'),
    ));

    $second = UnmatchedBrandModelCandidate::where('model_name_raw', 'Camry')->first();

    expect(UnmatchedBrandModelCandidate::count())->toBe(1);
    expect($second->first_seen_at->equalTo($firstSeenAt))->toBeTrue();
    expect($second->last_seen_at->greaterThan($firstSeenAt))->toBeTrue();
});

it('merges Cyrillic/Latin visual duplicates in the pending queue via deduplicatePendingCandidates', function () {
    $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Audi', 'model_name' => 'A5', 'url' => 'https://www.olx.uz/x/audi/a5/'),
        array('brand_name' => 'Audi', 'model_name' => 'А5', 'url' => 'https://www.olx.uz/x/audi/a5-cyrillic/'), // "А5" — kirillcha А
    ));

    expect(UnmatchedBrandModelCandidate::count())->toBe(2);

    $result = $this->service->deduplicatePendingCandidates();

    expect($result['merged'])->toBe(1);
    expect(UnmatchedBrandModelCandidate::count())->toBe(1);
});

it('does not merge genuinely different model names', function () {
    $this->service->processDiscoveredCombinations($this->source, array(
        array('brand_name' => 'Audi', 'model_name' => 'A5', 'url' => 'https://www.olx.uz/x/audi/a5/'),
        array('brand_name' => 'Audi', 'model_name' => 'A6', 'url' => 'https://www.olx.uz/x/audi/a6/'),
    ));

    $result = $this->service->deduplicatePendingCandidates();

    expect($result['merged'])->toBe(0);
    expect(UnmatchedBrandModelCandidate::count())->toBe(2);
});
