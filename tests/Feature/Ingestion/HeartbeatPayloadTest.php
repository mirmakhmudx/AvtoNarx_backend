<?php

use App\Models\ParserClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('stores parser status sent via heartbeat (TZ 8.5)', function () {
    $client = ParserClient::create([
        'name' => 'Heartbeat parser',
        'is_active' => true,
        'allowed_source_ids' => [],
    ]);

    Sanctum::actingAs($client, ['*']);

    $this->postJson('/api/v1/ingestion/heartbeat', [
        'parser_version' => '2.1.0',
        'hostname_hash' => str_repeat('b', 64),
        'queue_size' => 42,
        'last_run_at' => now()->toIso8601String(),
    ])->assertOk();

    $client->refresh();

    expect($client->parser_version)->toBe('2.1.0');
    expect($client->hostname_hash)->toBe(str_repeat('b', 64));
    expect($client->queue_size)->toBe(42);
    expect($client->last_run_at)->not->toBeNull();
    expect($client->last_heartbeat_at)->not->toBeNull();
    expect($client->last_seen_at)->not->toBeNull();
});

it('heartbeat works with an empty body (all fields optional)', function () {
    $client = ParserClient::create([
        'name' => 'Heartbeat parser 2',
        'is_active' => true,
        'allowed_source_ids' => [],
    ]);

    Sanctum::actingAs($client, ['*']);

    $this->postJson('/api/v1/ingestion/heartbeat', [])->assertOk();

    $client->refresh();
    expect($client->last_heartbeat_at)->not->toBeNull();
});
