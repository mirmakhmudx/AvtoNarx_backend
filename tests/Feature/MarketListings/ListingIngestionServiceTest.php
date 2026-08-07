<?php
 
use App\DTO\ListingData;
use App\Enums\EntityType;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\ListingPriceSnapshot;
use App\Models\MarketListing;
use App\Models\Source;
use App\Services\Catalog\CatalogAliasService;
use App\Services\MarketListings\ListingIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
 
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
 
    $this->service = app(ListingIngestionService::class);
});
 
function makeListingData(array $overrides = array()): ListingData
{
    return ListingData::fromArray(array_merge(array(
        'source_id' => 1,
        'external_id' => 'olx-test-1',
        'canonical_url' => 'https://www.olx.uz/d/obyavlenie/test-1.html',
        'brand_raw' => 'Chevrolet',
        'model_raw' => 'Cobalt',
        'year' => 2021,
        'price_amount' => 145000000,
        'currency' => 'UZS',
        'condition' => 'used',
        'seller_type' => 'private',
    ), $overrides));
}
 
it('creates a new listing when it does not exist yet', function () {
    $listing = $this->service->ingest(makeListingData());
 
    expect(MarketListing::count())->toBe(1);
    expect($listing->price_amount)->toBe(145000000);
    // status (lifecycle: active/inactive) va normalization_status (moslik:
    // matched/pending) — ikki xil maydon. Yangi ko'rilgan e'lon har doim
    // "active" bo'ladi; hali alias yo'qligi normalization_status'da ko'rinadi.
    expect($listing->status->value)->toBe('active');
    expect($listing->normalization_status->value)->toBe('pending'); // hali alias yo'q
});
 
it('resolves brand/model via alias and marks as matched', function () {
    $aliasService = app(CatalogAliasService::class);
    $brandAlias = $aliasService->createPendingAlias(EntityType::Brand, $this->brand->id, 'Chevrolet', $this->source->id);
    $aliasService->verify($brandAlias);
    $modelAlias = $aliasService->createPendingAlias(EntityType::Model, $this->model->id, 'Cobalt', $this->source->id);
    $aliasService->verify($modelAlias);
 
    $listing = $this->service->ingest(makeListingData());
 
    expect($listing->normalization_status->value)->toBe('matched');
    expect($listing->brand_id)->toBe($this->brand->id);
    expect($listing->model_id)->toBe($this->model->id);
});
 
it('does not create a duplicate when content_hash is unchanged', function () {
    $this->service->ingest(makeListingData());
    $this->service->ingest(makeListingData());
 
    expect(MarketListing::count())->toBe(1);
});
 
it('updates price and creates a snapshot when price changes', function () {
    $this->service->ingest(makeListingData(array('price_amount' => 145000000)));
    $this->service->ingest(makeListingData(array('price_amount' => 150000000)));
 
    expect(MarketListing::count())->toBe(1);
 
    $listing = MarketListing::first();
    expect($listing->price_amount)->toBe(150000000);
    expect(ListingPriceSnapshot::where('market_listing_id', $listing->id)->count())->toBe(2);
});
 
it('uses known_brand_id and known_model_id directly, bypassing alias lookup', function () {
    $listing = $this->service->ingest(makeListingData(array(
        'brand_raw' => 'Chevrolet Renamed By Admin',
        'model_raw' => 'Cobalt Renamed By Admin',
        'known_brand_id' => $this->brand->id,
        'known_model_id' => $this->model->id,
    )));
 
    expect($listing->normalization_status->value)->toBe('matched');
    expect($listing->brand_id)->toBe($this->brand->id);
    expect($listing->model_id)->toBe($this->model->id);
});
 
it('rejects a "dogovornaya"-style listing is not applicable here (handled by MoneyExtractor upstream), but accepts valid price', function () {
    // Backend'da narx ListingSanityChecker'dan o'tishi kerak (TZ 11: "aniq
    // to'liqsiz narxlar chiqarib tashlanadi"). 1 UZS kabi qiymatlar
    // implausible_price sifatida rad etiladi — shuning uchun bu yerda
    // haqiqiy, ishonchli narx ishlatiladi.
    $listing = $this->service->ingest(makeListingData(array('price_amount' => 150_000_000)));
 
    expect($listing->price_amount)->toBe(150_000_000);
});
