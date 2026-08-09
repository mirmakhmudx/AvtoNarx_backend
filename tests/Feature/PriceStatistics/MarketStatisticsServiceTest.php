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

    $this->brand = Brand::create(['name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1]);
    $this->model = CarModel::create(['brand_id' => $this->brand->id, 'name' => 'Cobalt', 'slug' => 'cobalt', 'is_active' => true]);

    $this->service = app(MarketStatisticsService::class);
});

function makeMatchedListing(int $brandId, int $modelId, ?int $year, array $overrides = []): MarketListing
{
    static $counter = 0;
    $counter++;

    return MarketListing::create(array_merge([
        'source_id' => 1,
        'external_id' => 'stat-test-'.$counter,
        'canonical_url' => 'https://www.olx.uz/d/obyavlenie/stat-test-'.$counter.'.html',
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
    ], $overrides));
}

it('creates statistics with correct median/mean/percentiles when sample size meets the minimum', function () {
    // 100M dan 190M gacha, 10M qadam bilan — 10 ta baravar taqsimlangan qiymat.
    $amounts = [100_000_000, 110_000_000, 120_000_000, 130_000_000, 140_000_000, 150_000_000, 160_000_000, 170_000_000, 180_000_000, 190_000_000];

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2021, [
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ]);
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
        makeMatchedListing($this->brand->id, $this->model->id, 2022, [
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
        ]);
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2022);

    expect($stat)->toBeNull();
    expect(MarketPriceStatistic::where('brand_id', $this->brand->id)->where('model_id', $this->model->id)->where('year', 2022)->count())->toBe(0);
});

it('deletes a previously created statistic when the sample later drops below the minimum', function () {
    $listings = [];

    for ($i = 0; $i < 10; $i++) {
        $listings[] = makeMatchedListing($this->brand->id, $this->model->id, 2023, [
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
        ]);
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2023);
    expect($stat)->not->toBeNull();

    // 5 tasini "inactive" qilamiz — endi faqat 5 ta faol qoladi, chegaradan kam.
    foreach (array_slice($listings, 0, 5) as $listing) {
        $listing->update(['status' => 'inactive']);
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
        makeMatchedListing($this->brand->id, $this->model->id, 2024, [
            'price_amount' => 120_000_000,
            'price_uzs' => null, // hali konvertatsiya qilinmagan, lekin UZS
            'currency' => 'UZS',
        ]);
    }

    for ($i = 0; $i < 3; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2024, [
            'price_amount' => 120_000_000,
            'price_uzs' => 120_000_000, // allaqachon konvertatsiya qilingan
            'currency' => 'UZS',
        ]);
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2024);

    expect($stat)->not->toBeNull();
    // Barcha 10 tasi ham hisoblanishi kerak (7 tasi fallback orqali, 3 tasi to'g'ridan-to'g'ri).
    expect($stat->sample_size)->toBe(10);
    expect($stat->median_price_uzs)->toBe(120_000_000);
});

it('does not count non-UZS listings whose price_uzs has not been converted yet', function () {
    for ($i = 0; $i < 9; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2025, [
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
            'currency' => 'UZS',
        ]);
    }

    // Bitta USD yozuv, price_uzs hali NULL (konvertatsiya kutilmoqda) — bu
    // hisoblanmasligi kerak, chunki noaniq narx bilan statistikaga qo'shib
    // bo'lmaydi.
    makeMatchedListing($this->brand->id, $this->model->id, 2025, [
        'price_amount' => 9_000,
        'price_uzs' => null,
        'currency' => 'USD',
    ]);

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2025);

    // Faqat 9 ta UZS hisoblanadi — bu MIN_SAMPLE_SIZE (10) dan kam, shuning
    // uchun statistika umuman yaratilmasligi kerak.
    expect($stat)->toBeNull();
});

it('excludes a clear outlier via IQR filtering once sample_size reaches the IQR threshold (TZ: 20)', function () {
    // 20 ta baravar taqsimlangan qiymat (100M dan 290M gacha, 10M qadam bilan).
    $amounts = [];
    for ($i = 0; $i < 20; $i++) {
        $amounts[] = 100_000_000 + ($i * 10_000_000);
    }

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2026, [
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ]);
    }

    // Aniq outlier — jami tanlanma 21 taga yetadi, ya'ni IQR chegarasi (20) ga yetadi/oshadi.
    makeMatchedListing($this->brand->id, $this->model->id, 2026, [
        'price_amount' => 900_000_000,
        'price_uzs' => 900_000_000,
    ]);

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2026);

    expect($stat)->not->toBeNull();
    expect($stat->sample_size)->toBe(20); // outlier IQR orqali chiqarib tashlangach
    expect($stat->excluded_count)->toBe(1);
    expect($stat->median_price_uzs)->toBe(195_000_000);
    expect($stat->max_price_uzs)->toBe(290_000_000); // 900M statistikaga kirmagan
});

it('does NOT apply IQR when sample_size is below the IQR threshold — only global bounds apply (TZ 11-bo\'lim, 4-bosqich)', function () {
    // 10 ta oddiy narx + 1 ta juda katta narx — jami 11 ta, IQR chegarasi (20) dan kam.
    $amounts = [100_000_000, 110_000_000, 120_000_000, 130_000_000, 140_000_000, 150_000_000, 160_000_000, 170_000_000, 180_000_000, 190_000_000];

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2027, [
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ]);
    }

    makeMatchedListing($this->brand->id, $this->model->id, 2027, [
        'price_amount' => 900_000_000,
        'price_uzs' => 900_000_000,
    ]);

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2027);

    expect($stat)->not->toBeNull();
    // Tanlanma (11) IQR chegarasidan (20) kam bo'lgani uchun 900M chiqarib tashlanmaydi —
    // u global chegaralar (standart: 3M-2B so'm) ichida qoladi.
    expect($stat->sample_size)->toBe(11);
    expect($stat->excluded_count)->toBe(0);
    expect($stat->max_price_uzs)->toBe(900_000_000);
});

it('excludes prices outside the configured global bounds regardless of sample size (TZ 11-bo\'lim, 1-2-bosqich)', function () {
    $amounts = [100_000_000, 110_000_000, 120_000_000, 130_000_000, 140_000_000, 150_000_000, 160_000_000, 170_000_000, 180_000_000, 190_000_000];

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2028, [
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ]);
    }

    // "Aniq to'liqsiz" narx — global minimumdan (standart 3M so'm) past.
    makeMatchedListing($this->brand->id, $this->model->id, 2028, [
        'price_amount' => 500_000,
        'price_uzs' => 500_000,
    ]);

    // Realistik bo'lmagan haddan tashqari katta narx — global maksimumdan (standart 2B so'm) yuqori.
    makeMatchedListing($this->brand->id, $this->model->id, 2028, [
        'price_amount' => 3_000_000_000,
        'price_uzs' => 3_000_000_000,
    ]);

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2028);

    expect($stat)->not->toBeNull();
    // Jami 12 ta yuborilgan, lekin 2 tasi global chegaradan tashqarida — 10 ta qoladi.
    // 10 < IQR chegarasi (20), shuning uchun IQR qo'llanilmaydi, faqat global chegaralar ishlaydi.
    expect($stat->sample_size)->toBe(10);
    expect($stat->excluded_count)->toBe(2);
    expect($stat->min_price_uzs)->toBe(100_000_000);
    expect($stat->max_price_uzs)->toBe(190_000_000);
});

it('counts available listings regardless of price validity', function () {
    for ($i = 0; $i < 4; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2020, [
            'price_amount' => 100_000_000,
            'price_uzs' => null,
        ]);
    }

    $count = $this->service->countAvailableListings($this->brand->id, $this->model->id, 2020);

    expect($count)->toBe(4);
});

it('excludes NEW-condition listings from the secondary-market sample (TZ 11-bo\'lim)', function () {
    // 9 ta haqiqiy "used" e'lon.
    for ($i = 0; $i < 9; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2019, [
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
            'condition' => 'used',
        ]);
    }

    // 5 ta YANGI ('new') mashina — ular official_offers'ga tegishli va
    // ikkilamchi bozor medianasiga umuman kirmasligi kerak.
    for ($i = 0; $i < 5; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2019, [
            'price_amount' => 200_000_000,
            'price_uzs' => 200_000_000,
            'condition' => 'new',
        ]);
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2019);

    // 'new'lar chiqarilgach faqat 9 ta "used" qoladi — bu MIN_SAMPLE_SIZE (10)
    // dan kam, shuning uchun statistika yaratilmaydi. Bu 'new'larning
    // tanlanmaga umuman qo'shilmayotganini isbotlaydi (aks holda 14 ta bo'lardi).
    expect($stat)->toBeNull();

    // countAvailableListings ham faqat "used"larni sanashi kerak.
    expect($this->service->countAvailableListings($this->brand->id, $this->model->id, 2019))->toBe(9);
});

it('excludes listings older than the freshness window (72h) from the sample (TZ 11-bo\'lim)', function () {
    // 10 ta yangi (last_seen_at = now) "used" e'lon — normalda statistika chiqishi kerak.
    $listings = [];
    for ($i = 0; $i < 10; $i++) {
        $listings[] = makeMatchedListing($this->brand->id, $this->model->id, 2018, [
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
            'last_seen_at' => now(),
        ]);
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2018);
    expect($stat)->not->toBeNull();
    expect($stat->sample_size)->toBe(10);

    // Endi 3 ta yozuvni 72 soatdan eski qilamiz — ular hali "active" bo'lsa
    // ham (ExpireStaleListingsJob hali ishlamagan bo'lishi mumkin),
    // statistikaga kirmasligi kerak. UPDATE...LIMIT ishlatmaymiz (Postgres
    // uni qo'llab-quvvatlamaydi) — aniq ID'lar bo'yicha yangilaymiz.
    foreach (array_slice($listings, 0, 3) as $listing) {
        $listing->update(['last_seen_at' => now()->subHours(80)]);
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2018);

    // 3 tasi eskirgan → 7 ta qoladi → MIN_SAMPLE_SIZE (10) dan kam → null.
    expect($stat)->toBeNull();
});
