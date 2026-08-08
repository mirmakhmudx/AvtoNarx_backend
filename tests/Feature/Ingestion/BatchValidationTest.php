<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\MarketListing;
use App\Models\ParserClient;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->source = Source::create([
        'code' => 'olx_uz',
        'name' => 'OLX.uz',
        'type' => 'marketplace',
        'base_url' => 'https://www.olx.uz',
        'is_active' => true,
        'ingestion_enabled' => true,
        'trust_level' => 'unverified',
        'settings' => [],
    ]);

    $this->brand = Brand::create(['name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1]);
    $this->model = CarModel::create(['brand_id' => $this->brand->id, 'name' => 'Cobalt', 'slug' => 'cobalt', 'is_active' => true]);

    $this->client = ParserClient::create([
        'name' => 'Validation test parser',
        'is_active' => true,
        'allowed_source_ids' => [$this->source->id],
    ]);

    Sanctum::actingAs($this->client, ['*']);
});

// Bitta item bo'yicha to'liq, TO'G'RI batch — kerakli maydonni override qilib sinaymiz.
function batchWithItem(array $itemOverrides = [], array $removeKeys = []): array
{
    $item = array_merge([
        'external_id' => 'olx-'.Str::random(8),
        'url' => 'https://www.olx.uz/d/obyavlenie/test.html',
        'brand' => 'Chevrolet',
        'model' => 'Cobalt',
        'year' => 2021,
        'price' => ['amount' => 145000000, 'currency' => 'UZS'],
        'observed_at' => now()->toIso8601String(),
        'content_hash' => hash('sha256', 'x-'.Str::random(8)),
    ], $itemOverrides);

    foreach ($removeKeys as $key) {
        unset($item[$key]);
    }

    return [
        'batch_id' => (string) Str::uuid(),
        'source' => 'olx_uz',
        'mode' => 'snapshot',
        'collected_at' => now()->toIso8601String(),
        'items' => [$item],
    ];
}

function postBatch(array $payload): TestResponse
{
    return test()
        ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/ingestion/market-listings/batches', $payload);
}

it('accepts a fully valid batch (202) — sanity baseline', function () {
    postBatch(batchWithItem())->assertStatus(202);
    expect(MarketListing::count())->toBe(1);
});

it('rejects an item with a MISSING year (TZ 8.2)', function () {
    postBatch(batchWithItem([], ['year']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.year' => 'invalid_year']);
});

it('rejects an item with an out-of-range year', function () {
    postBatch(batchWithItem(['year' => 1900]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.year' => 'invalid_year']);
});

it('rejects an item with a MISSING content_hash (TZ 8.2)', function () {
    postBatch(batchWithItem([], ['content_hash']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.content_hash' => 'invalid_content_hash']);
});

it('rejects an item with a non-hex content_hash', function () {
    postBatch(batchWithItem(['content_hash' => str_repeat('z', 64)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.content_hash' => 'invalid_content_hash']);
});

it('rejects an item whose URL is on a different domain than the source (invalid_url_domain, TZ 8.2)', function () {
    postBatch(batchWithItem(['url' => 'https://evil.example.com/fake-car']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.url' => 'invalid_url_domain']);
});

it('accepts a URL on a subdomain of the source domain', function () {
    // uz.olx.uz — www.olx.uz manbasining registrable domeni (olx.uz) ichida.
    postBatch(batchWithItem(['url' => 'https://uz.olx.uz/d/obyavlenie/test.html']))
        ->assertStatus(202);
});
