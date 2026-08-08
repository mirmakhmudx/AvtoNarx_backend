<?php

use App\Jobs\ProcessIngestionBatchJob;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\IngestionBatch;
use App\Models\IngestionItemError;
use App\Models\ParserClient;
use App\Models\Source;
use App\Services\MarketListings\ListingIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

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

    $this->client = ParserClient::create(array(
        'name' => 'Job test parser',
        'is_active' => true,
        'allowed_source_ids' => array($this->source->id),
    ));
});

function makeBatch(string $status): IngestionBatch
{
    return IngestionBatch::create(array(
        'id' => (string) Str::uuid(),
        'parser_client_id' => test()->client->id,
        'source_id' => test()->source->id,
        'dataset' => 'market_listings',
        'mode' => 'snapshot',
        'idempotency_key' => (string) Str::uuid(),
        'payload_checksum' => hash('sha256', (string) Str::uuid()),
        'collected_at' => now(),
        'received_at' => now(),
        'status' => $status,
        'items_total' => 0,
    ));
}

it('marks a stuck (processing) batch as failed when the whole job fails (TZ 15)', function () {
    $batch = makeBatch('processing');

    $job = new ProcessIngestionBatchJob($batch->id, array());
    $job->failed(new RuntimeException('database connection gone'));

    $batch->refresh();

    expect($batch->status)->toBe('failed');
    expect($batch->error_summary)->not->toBeNull();
    expect($batch->error_summary['message'])->toContain('database connection gone');
    expect($batch->completed_at)->not->toBeNull();
});

it('does NOT overwrite an already-completed batch from failed()', function () {
    $batch = makeBatch('completed');

    (new ProcessIngestionBatchJob($batch->id, array()))->failed(new RuntimeException('late failure'));

    $batch->refresh();

    // completed bo'lgan batch 'failed'ga o'zgarmasligi kerak.
    expect($batch->status)->toBe('completed');
});

it('clears item errors from a previous attempt so a retry does not duplicate them', function () {
    $batch = makeBatch('received');

    // Avvalgi urinishdan qolgan 3 ta "eski" xato yozuvini simulyatsiya qilamiz.
    foreach (range(0, 2) as $i) {
        IngestionItemError::create(array(
            'batch_id' => $batch->id,
            'item_index' => $i,
            'external_id' => "old-$i",
            'code' => 'stale_from_previous_try',
            'field' => null,
            'message' => 'old attempt',
        ));
    }

    expect($batch->itemErrors()->count())->toBe(3);

    // Bitta rad etiladigan item (1 UZS — sanity checker rad etadi).
    $items = array(array(
        'external_id' => 'x1',
        'url' => 'https://www.olx.uz/d/obyavlenie/test.html',
        'brand' => 'Chevrolet',
        'model' => 'Cobalt',
        'year' => 2021,
        'price' => array('amount' => 1, 'currency' => 'UZS'),
        'content_hash' => hash('sha256', 'x1'),
    ));

    (new ProcessIngestionBatchJob($batch->id, $items))->handle(app(ListingIngestionService::class));

    $batch->refresh();

    expect($batch->itemErrors()->count())->toBe(1);
    expect($batch->items_rejected)->toBe(1);
    expect($batch->items_accepted)->toBe(0);
});
