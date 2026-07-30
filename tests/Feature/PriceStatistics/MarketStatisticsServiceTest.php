<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\MarketListing;
use App\Models\MarketPriceStatistic;
use App\Models\Source;
use App\Services\PriceStatistics\MarketStatisticsService;
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

    $this->service = app(MarketStatisticsService::class);
});

/**
 * Har chaqiruvda unikal external_id/content_hash bilan "matched" va "active"
 * MarketListing yaratadi — statistikaga kirishi kerak bo'lgan standart holat.
 */
function makeMatchedListing(int $brandId, int $modelId, ?int $year, array $overrides = array()): MarketListing
{
    static $counter = 0;
    $counter++;

    return MarketListing::create(array_merge(array(
        'source_id' => 1,
        'external_id' => 'stat-test-' . $counter,
        'canonical_url' => 'https://www.olx.uz/d/obyavlenie/stat-test-' . $counter . '.html',
        'brand_raw' => 'Chevrolet',
        'model_raw' => 'Cobalt',
        'brand_id' => $brandId,
        'model_id' => $modelId,
        'normalization_status' => 'matched',
        'year' => $year,
        'price_amount' => 100000000,
        'currency' => 'UZS',
        'price_uzs' => null,
        'condition' => 'used',
        'seller_type' => 'private',
        'status' => 'active',
        'content_hash' => bin2hex(random_bytes(16)),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'missing_runs' => 0,
    ), $overrides));
}

it('creates statistics with correct median/mean/percentiles when sample size meets the minimum', function () {
    // 100M dan 190M gacha, 10M qadam bilan — 10 ta baravar taqsimlangan qiymat.
    $amounts = array(100_000_000, 110_000_000, 120_000_000, 130_000_000, 140_000_000, 150_000_000, 160_000_000, 170_000_000, 180_000_000, 190_000_000);

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2021, array(
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ));
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2021);

    expect($stat)->not->toBeNull();
    expect($stat->sample_size)->toBe(10);
    expect($stat->excluded_count)->toBe(0);
    expect($stat->median_price_uzs)->toBe(145_000_000);
    expect($stat->mean_price_uzs)->toBe(145_000_000);
    expect($stat->min_price_uzs)->toBe(100_000_000);
    expect($stat->max_price_uzs)->toBe(190_000_000);
    expect($stat->p25_price_uzs)->toBe(122_500_000);
    expect($stat->p75_price_uzs)->toBe(167_500_000);
});

it('returns null and does not create statistics when sample size is below the minimum', function () {
    // Faqat 5 ta — MIN_SAMPLE_SIZE (10) dan kam.
    for ($i = 0; $i < 5; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2022, array(
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
        ));
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2022);

    expect($stat)->toBeNull();
    expect(MarketPriceStatistic::where('brand_id', $this->brand->id)->where('model_id', $this->model->id)->where('year', 2022)->count())->toBe(0);
});

it('deletes a previously created statistic when the sample later drops below the minimum', function () {
    $listings = array();

    for ($i = 0; $i < 10; $i++) {
        $listings[] = makeMatchedListing($this->brand->id, $this->model->id, 2023, array(
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
        ));
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2023);
    expect($stat)->not->toBeNull();

    // 5 tasini "inactive" qilamiz — endi faqat 5 ta faol qoladi, chegaradan kam.
    foreach (array_slice($listings, 0, 5) as $listing) {
        $listing->update(array('status' => 'inactive'));
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2023);

    expect($stat)->toBeNull();
    expect(MarketPriceStatistic::where('brand_id', $this->brand->id)->where('model_id', $this->model->id)->where('year', 2023)->count())->toBe(0);
});

it('falls back to price_amount per-row when price_uzs is null for UZS listings (regresion for the "all-or-nothing" bug)', function () {
    // Ayrim yozuvlarda price_uzs to'ldirilgan (konvertatsiya bajarilgan),
    // ayrimlarida esa hali NULL (masalan konvertatsiya joby hali ishlamagan).
    // Eski (xato) kod: agar HATTO BITTA yozuvda price_uzs bo'lsa, price_uzs'i
    // yo'q qolgan HAMMA boshqa yozuvlarni tashlab yuborardi.
    for ($i = 0; $i < 7; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2024, array(
            'price_amount' => 120_000_000,
            'price_uzs' => null, // hali konvertatsiya qilinmagan, lekin UZS
            'currency' => 'UZS',
        ));
    }

    for ($i = 0; $i < 3; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2024, array(
            'price_amount' => 120_000_000,
            'price_uzs' => 120_000_000, // allaqachon konvertatsiya qilingan
            'currency' => 'UZS',
        ));
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2024);

    expect($stat)->not->toBeNull();
    // Barcha 10 tasi ham hisoblanishi kerak (7 tasi fallback orqali, 3 tasi to'g'ridan-to'g'ri).
    expect($stat->sample_size)->toBe(10);
    expect($stat->median_price_uzs)->toBe(120_000_000);
});

it('does not count non-UZS listings whose price_uzs has not been converted yet', function () {
    for ($i = 0; $i < 9; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2025, array(
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
            'currency' => 'UZS',
        ));
    }

    // Bitta USD yozuv, price_uzs hali NULL (konvertatsiya kutilmoqda) — bu
    // hisoblanmasligi kerak, chunki noaniq narx bilan statistikaga qo'shib
    // bo'lmaydi.
    makeMatchedListing($this->brand->id, $this->model->id, 2025, array(
        'price_amount' => 9_000,
        'price_uzs' => null,
        'currency' => 'USD',
    ));

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2025);

    // Faqat 9 ta UZS hisoblanadi — bu MIN_SAMPLE_SIZE (10) dan kam, shuning
    // uchun statistika umuman yaratilmasligi kerak.
    expect($stat)->toBeNull();
});

it('excludes a clear outlier via IQR filtering and reports the correct excluded_count', function () {
    $amounts = array(100_000_000, 110_000_000, 120_000_000, 130_000_000, 140_000_000, 150_000_000, 160_000_000, 170_000_000, 180_000_000, 190_000_000);

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2026, array(
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ));
    }

    // Aniq outlier — qolgan 10 tadan keskin farq qiladi.
    makeMatchedListing($this->brand->id, $this->model->id, 2026, array(
        'price_amount' => 900_000_000,
        'price_uzs' => 900_000_000,
    ));

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2026);

    expect($stat)->not->toBeNull();
    expect($stat->sample_size)->toBe(10); // outlier chiqarib tashlangach
    expect($stat->excluded_count)->toBe(1);
    expect($stat->median_price_uzs)->toBe(145_000_000);
    expect($stat->max_price_uzs)->toBe(190_000_000); // 900M statistikaga kirmagan
});

it('counts available listings regardless of price validity', function () {
    for ($i = 0; $i < 4; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2020, array(
            'price_amount' => 100_000_000,
            'price_uzs' => null,
        ));
    }

    $count = $this->service->countAvailableListings($this->brand->id, $this->model->id, 2020);

    expect($count)->toBe(4);
});
