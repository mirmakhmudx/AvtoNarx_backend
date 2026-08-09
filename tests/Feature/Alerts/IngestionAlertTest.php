<?php

use App\Models\IngestionBatch;
use App\Models\ParserClient;
use App\Models\Source;
use App\Services\Alerts\IngestionAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeFailedBatch(): IngestionBatch
{
    $source = Source::create([
        'code' => 'olx_uz', 'name' => 'OLX.uz', 'type' => 'marketplace',
        'base_url' => 'https://www.olx.uz', 'is_active' => true,
        'ingestion_enabled' => true, 'trust_level' => 'unverified', 'settings' => [],
    ]);
    $client = ParserClient::create(['name' => 'p', 'is_active' => true, 'allowed_source_ids' => [$source->id]]);

    return IngestionBatch::create([
        'id' => (string) Str::uuid(),
        'parser_client_id' => $client->id,
        'source_id' => $source->id,
        'dataset' => 'market_listings',
        'mode' => 'snapshot',
        'idempotency_key' => (string) Str::uuid(),
        'payload_checksum' => hash('sha256', 'x'),
        'collected_at' => now(),
        'received_at' => now(),
        'status' => 'failed',
        'items_total' => 0,
    ]);
}

it('sends a Slack alert when a webhook is configured (TZ 15)', function () {
    Http::fake();
    config()->set('alerts.slack_webhook', 'https://hooks.slack.example/xyz');

    $batch = makeFailedBatch();
    app(IngestionAlertService::class)->batchFailed($batch, 'RuntimeException: boom');

    Http::assertSent(function ($request) use ($batch) {
        return str_contains($request->url(), 'hooks.slack.example')
            && str_contains((string) ($request['text'] ?? ''), (string) $batch->id);
    });
});

it('does NOT send any HTTP when no Slack webhook is configured', function () {
    Http::fake();
    config()->set('alerts.slack_webhook', '');

    app(IngestionAlertService::class)->batchFailed(makeFailedBatch(), 'boom');

    Http::assertNothingSent();
});
