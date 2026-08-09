<?php

use App\DTO\ListingData;
use App\Exceptions\SuspiciousListingRejectedException;
use App\Models\MarketListing;
use App\Models\Source;
use App\Services\MarketListings\ListingIngestionService;
use App\Services\Parser\Extraction\ContentHashBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $this->service = app(ListingIngestionService::class);
});

it('uses the parser-provided content_hash (ContentHashBuilder) instead of recomputing (TZ 19)', function () {
    $builder = new ContentHashBuilder;
    $hash = $builder->build('olx_uz', 'ext-1', 'https://www.olx.uz/x', 'Chevrolet', 'Cobalt', 2021, 145000000, 'UZS', 'used');

    $listing = $this->service->ingest(ListingData::fromArray([
        'source_id' => $this->source->id,
        'external_id' => 'ext-1',
        'canonical_url' => 'https://www.olx.uz/x',
        'brand_raw' => 'Chevrolet',
        'model_raw' => 'Cobalt',
        'year' => 2021,
        'price_amount' => 145000000,
        'currency' => 'UZS',
        'condition' => 'used',
        'content_hash' => $hash,
    ]));

    expect($listing->content_hash)->toBe($hash);
});

it('rejects a non-UZS listing when no exchange rate exists (currency_conversion_failed)', function () {
    try {
        $this->service->ingest(ListingData::fromArray([
            'source_id' => $this->source->id,
            'external_id' => 'usd-1',
            'canonical_url' => 'https://www.olx.uz/usd',
            'brand_raw' => 'Chevrolet',
            'model_raw' => 'Cobalt',
            'year' => 2021,
            'price_amount' => 15000,
            'currency' => 'USD',
            'condition' => 'used',
            'content_hash' => str_repeat('a', 64),
        ]));

        $this->fail('SuspiciousListingRejectedException kutilgan edi, tashlanmadi.');
    } catch (SuspiciousListingRejectedException $e) {
        expect($e->errorCode())->toBe('currency_conversion_failed');
    }

    expect(MarketListing::count())->toBe(0);
});
