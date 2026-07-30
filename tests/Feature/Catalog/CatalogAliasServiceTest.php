<?php

use App\Enums\EntityType;
use App\Models\Brand;
use App\Models\CatalogAlias;
use App\Models\Source;
use App\Services\Catalog\CatalogAliasService;
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

    $this->service = app(CatalogAliasService::class);
});

it('returns null when no alias exists at all', function () {
    expect($this->service->resolve(EntityType::Brand, 'Chevrolet', $this->source->id))->toBeNull();
});

it('resolves a verified source-scoped alias', function () {
    $alias = $this->service->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);
    $this->service->verify($alias);

    expect($this->service->resolve(EntityType::Brand, 'Chevrolet', $this->source->id))->toBe($this->brand->id);
});

it('does not resolve an alias that has not been verified yet', function () {
    $this->service->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);

    expect($this->service->resolve(EntityType::Brand, 'Chevrolet', $this->source->id))->toBeNull();
});

it('falls back to a global (source_id null) verified alias when no source-specific one exists', function () {
    $alias = $this->service->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevy', null);
    $this->service->verify($alias);

    expect($this->service->resolve(EntityType::Brand, 'Chevy', $this->source->id))->toBe($this->brand->id);
});

it('prefers a source-specific alias over a global one when both exist for the same raw name', function () {
    $otherBrand = Brand::create(array('name' => 'Other', 'slug' => 'other', 'is_active' => true, 'sort_order' => 2));

    $global = $this->service->createPendingAlias(EntityType::Brand, $otherBrand->id, 'Chevrolet', null);
    $this->service->verify($global);

    $specific = $this->service->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);
    $this->service->verify($specific);

    expect($this->service->resolve(EntityType::Brand, 'Chevrolet', $this->source->id))->toBe($this->brand->id);
});

it('normalizes case and surrounding whitespace so differently-formatted raw names still match', function () {
    $alias = $this->service->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);
    $this->service->verify($alias);

    expect($this->service->resolve(EntityType::Brand, '  CHEVROLET  ', $this->source->id))->toBe($this->brand->id);
});

it('keeps brand and model aliases separate even with the same raw name', function () {
    $brandAlias = $this->service->createPendingAlias(EntityType::Brand, $this->brand->id, 'Cobalt', $this->source->id);
    $this->service->verify($brandAlias);

    expect($this->service->resolve(EntityType::Model, 'Cobalt', $this->source->id))->toBeNull();
});

it('createPendingAlias upserts instead of creating a duplicate row on repeated calls', function () {
    $this->service->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);
    $this->service->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);

    expect(CatalogAlias::count())->toBe(1);
});
