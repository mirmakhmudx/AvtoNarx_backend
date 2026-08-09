<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes Prometheus metrics when no token is configured (TZ 17)', function () {
    config()->set('metrics.token', '');

    $this->get('/metrics')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
        ->assertSee('avtonarx_ingestion_batches_total', false)
        ->assertSee('avtonarx_market_listings', false)
        ->assertSee('avtonarx_statistics_age_seconds', false)
        ->assertSee('avtonarx_sources_stale', false);
});

it('requires a bearer token when one is configured', function () {
    config()->set('metrics.token', 'secret-token');

    $this->get('/metrics')->assertStatus(403);
    $this->withHeaders(['Authorization' => 'Bearer wrong'])->get('/metrics')->assertStatus(403);
    $this->withHeaders(['Authorization' => 'Bearer secret-token'])->get('/metrics')->assertOk();
});

it('returns 404 when metrics are disabled', function () {
    config()->set('metrics.enabled', false);

    $this->get('/metrics')->assertStatus(404);
});
