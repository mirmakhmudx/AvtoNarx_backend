<?php

use App\Enums\EntityType;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\IngestionBatch;
use App\Models\ParserClient;
use App\Models\Source;
use App\Services\Catalog\CatalogAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->marketplaceSource = Source::create(array(
        'code' => 'olx_uz',
        'name' => 'OLX.uz',
        'type' => 'marketplace',
        'base_url' => 'https://www.olx.uz',
        'is_active' => true,
        'ingestion_enabled' => true,
        'trust_level' => 'unverified',
        'settings' => array(),
    ));

    $this->manufacturerSource = Source::create(array(
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

    $this->client = ParserClient::create(array(
        'name' => 'Test parser',
        'is_active' => true,
        'allowed_source_ids' => array($this->marketplaceSource->id, $this->manufacturerSource->id),
    ));
});

function actingAsParserClient(): void
{
    Sanctum::actingAs(test()->client, ['*']);
}

function validListingItem(array $overrides = array()): array
{
    return array_merge(array(
        'external_id' => 'olx-' . Str::random(8),
        'url' => 'https://www.olx.uz/d/obyavlenie/test.html',
        'brand' => 'Chevrolet',
        'model' => 'Cobalt',
        'year' => 2021,
        'price' => array('amount' => 145000000, 'currency' => 'UZS'),
        'observed_at' => now()->toIso8601String(),
        'content_hash' => hash('sha256', 'listing-' . Str::random(8)),
    ), $overrides);
}

function validListingBatchPayload(array $overrides = array()): array
{
    return array_merge(array(
        'batch_id' => (string) Str::uuid(),
        'source' => 'olx_uz',
        'mode' => 'snapshot',
        'collected_at' => now()->toIso8601String(),
        'items' => array(validListingItem()),
    ), $overrides);
}

function validOfferItem(array $overrides = array()): array
{
    return array_merge(array(
        'external_id' => 'offer-' . Str::random(8),
        'url' => 'https://avto.uzum.uz/cars/cobalt',
        'brand' => 'Chevrolet',
        'model' => 'Cobalt',
        'trim' => 'Style AT',
        'year' => 2026,
        'price' => array('amount' => 145000000, 'currency' => 'UZS'),
        'observed_at' => now()->toIso8601String(),
        'content_hash' => hash('sha256', 'offer-' . Str::random(8)),
    ), $overrides);
}

function validOfferBatchPayload(array $overrides = array()): array
{
    return array_merge(array(
        'batch_id' => (string) Str::uuid(),
        'source' => 'uzum_avto',
        'mode' => 'snapshot',
        'collected_at' => now()->toIso8601String(),
        'items' => array(validOfferItem()),
    ), $overrides);
}

// --- market-listings/batches ---

it('rejects market-listings batch requests without authentication', function () {
    $response = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload());

    $response->assertStatus(401);
});

it('rejects market-listings batch requests missing the Idempotency-Key header', function () {
    actingAsParserClient();

    $response = $this->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload());

    $response->assertStatus(422);
    expect(IngestionBatch::count())->toBe(0);
});

it('rejects market-listings batch requests with a non-UUID Idempotency-Key', function () {
    actingAsParserClient();

    $response = $this->withHeaders(array('Idempotency-Key' => 'not-a-uuid'))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload());

    $response->assertStatus(422);
});

it('rejects market-listings batch requests referencing an unknown source code', function () {
    actingAsParserClient();

    $response = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload(array('source' => 'no_such_source')));

    $response->assertStatus(422);
});

it('rejects market-listings batch requests for a source the client is not allowed to use', function () {
    $restrictedClient = ParserClient::create(array(
        'name' => 'Restricted client',
        'is_active' => true,
        'allowed_source_ids' => array($this->manufacturerSource->id), // olx_uz YO'Q ro'yxatda
    ));
    Sanctum::actingAs($restrictedClient, ['*']);

    $response = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload());

    $response->assertStatus(403);
});

it('accepts a valid market-listings batch, processes it synchronously, and stores the items', function () {
    actingAsParserClient();

    $response = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload());

    $response->assertStatus(202);
    $batchId = $response->json('data.batch_id');

    $batch = IngestionBatch::find($batchId);
    expect($batch)->not->toBeNull();
    expect($batch->dataset)->toBe('market_listings');
    expect($batch->status)->toBe('completed');
    expect($batch->items_accepted)->toBe(1);
    expect($batch->items_rejected)->toBe(0);
});

it('is idempotent: replaying the same Idempotency-Key does not create a second batch', function () {
    actingAsParserClient();
    $idempotencyKey = (string) Str::uuid();
    $payload = validListingBatchPayload();

    $first = $this->withHeaders(array('Idempotency-Key' => $idempotencyKey))
        ->postJson('/api/v1/ingestion/market-listings/batches', $payload);
    $second = $this->withHeaders(array('Idempotency-Key' => $idempotencyKey))
        ->postJson('/api/v1/ingestion/market-listings/batches', $payload);

    $first->assertStatus(202);
    $second->assertStatus(202);
    expect($second->json('data.batch_id'))->toBe($first->json('data.batch_id'));
    expect(IngestionBatch::count())->toBe(1);
});

it('returns 409 duplicate_batch_conflict when the same batch_id is resent with different content', function () {
    actingAsParserClient();
    $batchId = (string) Str::uuid();

    $first = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload(array('batch_id' => $batchId)));
    $first->assertStatus(202);

    // Xuddi shu batch_id, lekin BOSHQA Idempotency-Key va BOSHQA item tarkibi
    // (turli external_id) bilan qayta yuborilmoqda.
    $second = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload(array('batch_id' => $batchId)));

    $second->assertStatus(409);
    expect($second->json('code'))->toBe('duplicate_batch_conflict');
    expect(IngestionBatch::count())->toBe(1);
});

it('returns 409 when a different parser client reuses someone else\'s batch_id', function () {
    actingAsParserClient();
    $batchId = (string) Str::uuid();

    $first = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload(array('batch_id' => $batchId)));
    $first->assertStatus(202);

    $otherClient = ParserClient::create(array(
        'name' => 'Other parser',
        'is_active' => true,
        'allowed_source_ids' => array($this->marketplaceSource->id),
    ));
    Sanctum::actingAs($otherClient, ['*']);

    $second = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload(array('batch_id' => $batchId)));

    $second->assertStatus(409);
    expect($second->json('code'))->toBe('duplicate_batch_conflict');
});

it('forbids a parser client from reading another client\'s batch status', function () {
    actingAsParserClient();

    $response = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/market-listings/batches', validListingBatchPayload());
    $batchId = $response->json('data.batch_id');

    $otherClient = ParserClient::create(array(
        'name' => 'Other parser',
        'is_active' => true,
        'allowed_source_ids' => array($this->marketplaceSource->id),
    ));
    Sanctum::actingAs($otherClient, ['*']);

    $this->getJson("/api/v1/ingestion/batches/{$batchId}")->assertStatus(403);
    $this->getJson("/api/v1/ingestion/batches/{$batchId}/errors")->assertStatus(403);
});

// --- official-offers/batches ---

it('rejects official-offers batches from a marketplace-type source', function () {
    actingAsParserClient();

    $response = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/official-offers/batches', validOfferBatchPayload(array('source' => 'olx_uz')));

    $response->assertStatus(403);
    expect($response->json('code'))->toBe('source_not_allowed');
});

it('accepts official-offers batches from a manufacturer-type source and matches known catalog items', function () {
    actingAsParserClient();

    $aliasService = app(CatalogAliasService::class);
    $brandAlias = $aliasService->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->manufacturerSource->id);
    $aliasService->verify($brandAlias);
    $modelAlias = $aliasService->createPendingAlias(EntityType::Model, $this->model->id, 'Cobalt', $this->manufacturerSource->id);
    $aliasService->verify($modelAlias);

    $response = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/official-offers/batches', validOfferBatchPayload());

    $response->assertStatus(202);

    $batch = IngestionBatch::find($response->json('data.batch_id'));
    expect($batch->dataset)->toBe('official_offers');
    expect($batch->status)->toBe('completed');
    expect($batch->items_accepted)->toBe(1);
});

it('records an unmatched_brand item error when the official-offer brand is not in the catalog', function () {
    actingAsParserClient();

    // Hech qanday alias sozlanmagan — brend resolve bo'lmaydi.
    $response = $this->withHeaders(array('Idempotency-Key' => (string) Str::uuid()))
        ->postJson('/api/v1/ingestion/official-offers/batches', validOfferBatchPayload());

    $response->assertStatus(202);
    $batchId = $response->json('data.batch_id');

    $batch = IngestionBatch::find($batchId);
    expect($batch->status)->toBe('failed');
    expect($batch->items_accepted)->toBe(0);
    expect($batch->items_rejected)->toBe(1);

    $statusResponse = $this->getJson("/api/v1/ingestion/batches/{$batchId}");
    $statusResponse->assertStatus(200);
    expect($statusResponse->json('data.errors.0.code'))->toBe('unmatched_brand');
});

// --- batches/{id}, heartbeat, catalog ---

it('returns 404 for an unknown batch id', function () {
    actingAsParserClient();

    $response = $this->getJson('/api/v1/ingestion/batches/' . Str::uuid());

    $response->assertStatus(404);
});

it('heartbeat requires authentication and updates last_seen_at', function () {
    $unauthenticated = $this->postJson('/api/v1/ingestion/heartbeat');
    $unauthenticated->assertStatus(401);

    actingAsParserClient();
    expect($this->client->last_seen_at)->toBeNull();

    $response = $this->postJson('/api/v1/ingestion/heartbeat');

    $response->assertStatus(200);
    expect($this->client->refresh()->last_seen_at)->not->toBeNull();
});

it('catalog endpoint returns only active brands and models', function () {
    actingAsParserClient();

    $inactiveBrand = Brand::create(array('name' => 'Retired', 'slug' => 'retired', 'is_active' => false, 'sort_order' => 9));
    CarModel::create(array('brand_id' => $this->brand->id, 'name' => 'Discontinued', 'slug' => 'discontinued', 'is_active' => false));

    $response = $this->getJson('/api/v1/ingestion/catalog');

    $response->assertStatus(200);
    $brandNames = collect($response->json('data.brands'))->pluck('name');
    $modelNames = collect($response->json('data.models'))->pluck('name');

    expect($brandNames)->toContain('Chevrolet');
    expect($brandNames)->not->toContain('Retired');
    expect($modelNames)->toContain('Cobalt');
    expect($modelNames)->not->toContain('Discontinued');
});
