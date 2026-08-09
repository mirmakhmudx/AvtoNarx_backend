<?php

use App\Models\Source;
use App\Services\Parser\Adapters\UzumAvtoAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->source = Source::create([
        'code' => 'uzum_avto',
        'name' => 'Uzum Avto',
        'type' => 'marketplace',
        'base_url' => 'https://webview.uzumavto.uz',
        'is_active' => true,
        'ingestion_enabled' => true,
        'trust_level' => 'unverified',
        'settings' => [],
    ]);
    $this->adapter = app(UzumAvtoAdapter::class);
});

it('maps Uzum feed JSON into market listings with used condition', function () {
    $json = ['items' => [
        ['id' => 'abc123', 'brand' => 'Chevrolet', 'model' => 'Damas', 'year' => 2025, 'price' => 76419000, 'region' => 'Farg\'ona'],
    ]];

    $listings = $this->adapter->mapListings($json, $this->source);

    expect($listings)->toHaveCount(1);
    expect($listings[0]->modelRaw)->toBe('Damas');
    expect($listings[0]->brandRaw)->toBe('Chevrolet');
    expect($listings[0]->condition)->toBe('used');
    expect($listings[0]->priceAmount)->toBe(76419000);
    expect($listings[0]->contentHash)->not->toBeNull();
});

it('skips items without a price or model', function () {
    $json = ['items' => [
        ['id' => 'x1', 'brand' => 'Chevrolet'],
        ['id' => 'x2', 'model' => 'Cobalt', 'price' => 0],
    ]];

    expect($this->adapter->mapListings($json, $this->source))->toBeEmpty();
});
