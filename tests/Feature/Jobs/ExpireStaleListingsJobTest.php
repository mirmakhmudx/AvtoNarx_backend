<?php

use App\Jobs\ExpireStaleListingsJob;
use App\Models\MarketListing;
use App\Models\Source;
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
});

function makeStaleTestListing(array $overrides = array()): MarketListing
{
    return MarketListing::create(array_merge(array(
        'source_id' => test()->source->id,
        'external_id' => 'ext-' . Str::random(8),
        'canonical_url' => 'https://www.olx.uz/d/obyavlenie/test.html',
        'price_amount' => 100000000,
        'currency' => 'UZS',
        'status' => 'active',
        'content_hash' => hash('sha256', Str::random(8)),
        'last_seen_at' => now(),
    ), $overrides));
}

it('deactivates active listings whose last_seen_at is older than 72 hours', function () {
    $stale = makeStaleTestListing(array('last_seen_at' => now()->subHours(73)));

    ExpireStaleListingsJob::dispatchSync();

    expect($stale->refresh()->status->value)->toBe('inactive');
});

it('leaves recently-seen active listings untouched', function () {
    $fresh = makeStaleTestListing(array('last_seen_at' => now()->subHours(10)));

    ExpireStaleListingsJob::dispatchSync();

    expect($fresh->refresh()->status->value)->toBe('active');
});

it('does not resurrect listings that are already inactive', function () {
    $alreadyInactive = makeStaleTestListing(array('status' => 'inactive', 'last_seen_at' => now()->subHours(300)));

    ExpireStaleListingsJob::dispatchSync();

    expect($alreadyInactive->refresh()->status->value)->toBe('inactive');
});

it('preserves a listing right at the edge of the 72-hour window (not yet stale)', function () {
    $justInsideWindow = makeStaleTestListing(array('last_seen_at' => now()->subHours(71)->subMinutes(59)));

    ExpireStaleListingsJob::dispatchSync();

    expect($justInsideWindow->refresh()->status->value)->toBe('active');
});

it('processes multiple listings across sources in a single run', function () {
    $otherSource = Source::create(array(
        'code' => 'uzum_avto',
        'name' => 'Uzum Avto',
        'type' => 'manufacturer',
        'base_url' => 'https://avto.uzum.uz',
        'is_active' => true,
        'ingestion_enabled' => true,
        'trust_level' => 'official',
        'settings' => array(),
    ));

    $staleA = makeStaleTestListing(array('last_seen_at' => now()->subHours(100)));
    $staleB = makeStaleTestListing(array('source_id' => $otherSource->id, 'last_seen_at' => now()->subHours(200)));
    $fresh = makeStaleTestListing(array('last_seen_at' => now()->subHours(1)));

    ExpireStaleListingsJob::dispatchSync();

    expect($staleA->refresh()->status->value)->toBe('inactive');
    expect($staleB->refresh()->status->value)->toBe('inactive');
    expect($fresh->refresh()->status->value)->toBe('active');
});
