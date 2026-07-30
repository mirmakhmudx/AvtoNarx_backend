<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\OfficialOffer;
use App\Models\Source;
use App\Services\OfficialOffers\OfficialOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->source = Source::create(array(
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

    $this->service = app(OfficialOfferService::class);
});

function makeManualOffer(array $overrides = array()): OfficialOffer
{
    return OfficialOffer::create(array_merge(array(
        'source_id' => test()->source->id,
        'brand_id' => test()->brand->id,
        'model_id' => test()->model->id,
        'price_amount' => 145000000,
        'currency' => 'UZS',
        'publication_status' => 'published',
    ), $overrides));
}

it('create() sets pending status and computes price_uzs', function () {
    $offer = $this->service->create(array(
        'source_id' => $this->source->id,
        'brand_id' => $this->brand->id,
        'model_id' => $this->model->id,
        'price_amount' => 145000000,
        'currency' => 'UZS',
        'source_url' => 'https://avto.uzum.uz/cobalt',
    ));

    expect($offer->publication_status->value)->toBe('pending');
    expect($offer->price_uzs)->toBe(145000000);
    expect($offer->observed_at)->not->toBeNull();
});

it('publish() sets published/verified fields and records the verifying admin', function () {
    $offer = makeManualOffer(array('publication_status' => 'pending'));

    $updated = $this->service->publish($offer, 42);

    expect($updated->publication_status->value)->toBe('published');
    expect($updated->verified_by)->toBe(42);
    expect($updated->published_at)->not->toBeNull();
    expect($updated->verified_at)->not->toBeNull();
});

it('reject() sets status to rejected without touching other fields', function () {
    $offer = makeManualOffer(array('publication_status' => 'pending'));

    $updated = $this->service->reject($offer);

    expect($updated->publication_status->value)->toBe('rejected');
});

it('expireOutdated() expires only PUBLISHED offers whose valid_to has passed', function () {
    $expired = makeManualOffer(array('trim_name' => 'A', 'publication_status' => 'published', 'valid_to' => now()->subDay()));
    $stillValid = makeManualOffer(array('trim_name' => 'B', 'publication_status' => 'published', 'valid_to' => now()->addDay()));
    $pendingOld = makeManualOffer(array('trim_name' => 'C', 'publication_status' => 'pending', 'valid_to' => now()->subDay()));
    $noExpiry = makeManualOffer(array('trim_name' => 'D', 'publication_status' => 'published', 'valid_to' => null));

    $count = $this->service->expireOutdated();

    expect($count)->toBe(1);
    expect($expired->refresh()->publication_status->value)->toBe('expired');
    expect($stillValid->refresh()->publication_status->value)->toBe('published');
    expect($pendingOld->refresh()->publication_status->value)->toBe('pending');
    expect($noExpiry->refresh()->publication_status->value)->toBe('published');
});

it('cheapestForModel() returns the lowest-priced PUBLISHED offer, ignoring pending ones', function () {
    makeManualOffer(array('trim_name' => 'Expensive', 'publication_status' => 'published', 'price_amount' => 200000000));
    $cheapPublished = makeManualOffer(array('trim_name' => 'Cheap', 'publication_status' => 'published', 'price_amount' => 140000000));
    makeManualOffer(array('trim_name' => 'CheaperButPending', 'publication_status' => 'pending', 'price_amount' => 100000000));

    $result = $this->service->cheapestForModel($this->model->id);

    expect($result->id)->toBe($cheapPublished->id);
});

it('listPendingForModeration() returns only pending offers, oldest first', function () {
    $first = makeManualOffer(array('trim_name' => 'First', 'publication_status' => 'pending', 'created_at' => now()->subHours(2)));
    $second = makeManualOffer(array('trim_name' => 'Second', 'publication_status' => 'pending', 'created_at' => now()->subHour()));
    makeManualOffer(array('trim_name' => 'Published', 'publication_status' => 'published'));
    makeManualOffer(array('trim_name' => 'Rejected', 'publication_status' => 'rejected'));

    $result = $this->service->listPendingForModeration();

    expect($result)->toHaveCount(2);
    expect($result->first()->id)->toBe($first->id);
    expect($result->last()->id)->toBe($second->id);
});
