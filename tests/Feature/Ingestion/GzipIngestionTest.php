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
        'name' => 'Gzip test parser',
        'is_active' => true,
        'allowed_source_ids' => [$this->source->id],
    ]);

    Sanctum::actingAs($this->client, ['*']);
});

function gzipBatchPayload(): array
{
    return [
        'batch_id' => (string) Str::uuid(),
        'source' => 'olx_uz',
        'mode' => 'snapshot',
        'collected_at' => now()->toIso8601String(),
        'items' => [[
            'external_id' => 'olx-'.Str::random(8),
            'url' => 'https://www.olx.uz/d/obyavlenie/test.html',
            'brand' => 'Chevrolet',
            'model' => 'Cobalt',
            'year' => 2021,
            'price' => ['amount' => 145000000, 'currency' => 'UZS'],
            'observed_at' => now()->toIso8601String(),
            'content_hash' => hash('sha256', 'gz-'.Str::random(8)),
        ]],
    ];
}

/**
 * @param  'gzip'|'plain'  $bodyMode  'gzip' — tanani haqiqatan gzip qiladi;
 *                                    'plain' — gzip QILMAYDI (buzuq holatni sinash uchun),
 *                                    lekin Content-Encoding: gzip sarlavhasi baribir yuboriladi.
 */
function postGzipped(array $payload, string $bodyMode = 'gzip'): TestResponse
{
    $json = json_encode($payload);
    $body = $bodyMode === 'gzip' ? gzencode($json) : $json;

    return test()->call(
        'POST',
        '/api/v1/ingestion/market-listings/batches',
        [],
        [],
        [],
        [
            'HTTP_CONTENT_ENCODING' => 'gzip',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => (string) Str::uuid(),
        ],
        $body,
    );
}

it('accepts a gzip-encoded market-listings batch and stores the items (TZ 8.1)', function () {
    $response = postGzipped(gzipBatchPayload(), 'gzip');

    $response->assertStatus(202);
    expect(MarketListing::count())->toBe(1);
});

it('rejects a body that claims gzip but is not valid gzip with 400 invalid_gzip', function () {
    // gzip QILINMAGAN oddiy JSON, lekin Content-Encoding: gzip deb yuboriladi.
    $response = postGzipped(gzipBatchPayload(), 'plain');

    $response->assertStatus(400);
    $response->assertJsonPath('error.code', 'invalid_gzip');
});
