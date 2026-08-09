<?php

use App\Jobs\ExpireOfficialOffersJob;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\OfficialOffer;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->source = Source::create([
        'code' => 'uzum_avto',
        'name' => 'Uzum Avto',
        'type' => 'manufacturer',
        'base_url' => 'https://avto.uzum.uz',
        'is_active' => true,
        'ingestion_enabled' => true,
        'trust_level' => 'official',
        'settings' => [],
    ]);

    $this->brand = Brand::create(['name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1]);
    $this->model = CarModel::create(['brand_id' => $this->brand->id, 'name' => 'Cobalt', 'slug' => 'cobalt', 'is_active' => true]);
});

function makeExpiryTestOffer(array $overrides = []): OfficialOffer
{
    return OfficialOffer::create(array_merge([
        'source_id' => test()->source->id,
        'brand_id' => test()->brand->id,
        'model_id' => test()->model->id,
        'price_amount' => 145000000,
        'currency' => 'UZS',
        'publication_status' => 'published',
    ], $overrides));
}

it('delegates to OfficialOfferService::expireOutdated and expires overdue published offers', function () {
    $expired = makeExpiryTestOffer(['trim_name' => 'Expired', 'valid_to' => now()->subDay()]);
    $stillValid = makeExpiryTestOffer(['trim_name' => 'Valid', 'valid_to' => now()->addDay()]);

    ExpireOfficialOffersJob::dispatchSync();

    expect($expired->refresh()->publication_status->value)->toBe('expired');
    expect($stillValid->refresh()->publication_status->value)->toBe('published');
});

it('does not touch pending offers even if their valid_to has passed', function () {
    $pendingOld = makeExpiryTestOffer(['trim_name' => 'PendingOld', 'publication_status' => 'pending', 'valid_to' => now()->subDay()]);

    ExpireOfficialOffersJob::dispatchSync();

    expect($pendingOld->refresh()->publication_status->value)->toBe('pending');
});
