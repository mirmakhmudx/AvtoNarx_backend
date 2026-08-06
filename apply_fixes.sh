#!/usr/bin/env bash
# AvtoNarx backend — Fix #6 (statistika tanlanmasi: new + 72h filtr) va
# Fix #5 (statistika soatlik + Redis lock) uchun avtomatik qo'llash skripti.
# ISHLATISH: shu faylni loyiha ILDIZIGA (artisan fayli yonига) qo'ying va:
#   bash apply_fixes.sh
set -euo pipefail

if [ ! -f artisan ]; then
  echo "XATO: bu skript Laravel loyiha ILDIZIDAN ishga tushirilishi kerak (artisan fayli topilmadi)." >&2
  exit 1
fi

echo "Fayllar yozilmoqda..."

mkdir -p "$(dirname 'config/market_statistics.php')"
cat > 'config/market_statistics.php' << 'AVTONARX_EOF_9271'
<?php

return array(
    /*
    |--------------------------------------------------------------------------
    | Narxning global chegaralari (TZ 11-bo'lim, 1-bosqich)
    |--------------------------------------------------------------------------
    |
    | Bozor statistikasiga kiritishdan oldin har bir e'lon narxi shu
    | chegaralar bilan solishtiriladi. Diapazondan tashqarida qolgan
    | narxlar (shu jumladan 0 va "aniq to'liqsiz" — masalan xato bilan
    | kiritilgan juda kichik summalar) statistikaga umuman kirmaydi.
    | Standart qiymatlar so'mda, .env orqali sozlanadi.
    |
    */
    'global_min_price_uzs' => (int) env('MARKET_STATS_GLOBAL_MIN_PRICE_UZS', 3_000_000),
    'global_max_price_uzs' => (int) env('MARKET_STATS_GLOBAL_MAX_PRICE_UZS', 2_000_000_000),

    /*
    |--------------------------------------------------------------------------
    | IQR uchun minimal tanlanma hajmi (TZ 11-bo'lim, 3-4-bosqich)
    |--------------------------------------------------------------------------
    |
    | Global chegaralardan o'tgan tanlanma shu songa yetganidan keyingina
    | IQR (Q1 - 1.5*IQR, Q3 + 1.5*IQR) qo'llaniladi. Kichikroq tanlanmada
    | faqat yuqoridagi global chegaralar ishlatiladi — IQR statistik
    | jihatdan ishonchsiz bo'lib qoladi.
    |
    */
    'iqr_min_sample_size' => (int) env('MARKET_STATS_IQR_MIN_SAMPLE_SIZE', 20),

    /*
    |--------------------------------------------------------------------------
    | Nashr etish uchun minimal tanlanma (TZ 11-bo'lim, "Natija")
    |--------------------------------------------------------------------------
    |
    | 1-9 oralig'ida narx insufficient_sample sababi bilan yashiriladi.
    |
    */
    'min_sample_size' => (int) env('MARKET_STATS_MIN_SAMPLE_SIZE', 10),

    /*
    |--------------------------------------------------------------------------
    | Tanlanmaning maksimal "eskirish" oynasi (TZ 11-bo'lim)
    |--------------------------------------------------------------------------
    |
    | TZ: tanlanmaga "last_seen_at 72 soatdan eski bo'lmagan" e'lonlar kiradi.
    | Avval bu faqat ExpireStaleListingsJob'ga tayanardi (u active->inactive
    | qiladi), lekin o'sha job ishlamagan oynada eskirgan e'lonlar ham
    | statistikaga tushib qolardi. Endi hisoblash paytida BEVOSITA
    | last_seen_at bo'yicha ham cheklanadi.
    |
    */
    'sample_max_age_hours' => (int) env('MARKET_STATS_SAMPLE_MAX_AGE_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Statistikaga kiradigan condition qiymatlari (TZ 11-bo'lim)
    |--------------------------------------------------------------------------
    |
    | TZ: tanlanma — "used yoki tasdiqlangan unknown". Eng muhimi: YANGI
    | ('new') mashinalar ikkilamchi bozor medianasiga ARALASHMASLIGI kerak
    | — ular official_offers'ga tegishli. Shu sabab standart holatda 'new'
    | chiqarib tashlanadi.
    |
    | 'unknown' pragmatik sabab bilan kiritilgan: parser condition'ni ishonchli
    | aniqlay olmasa TZ bo'yicha 'unknown' yuboradi, ya'ni real hayotda ko'p
    | e'lon 'unknown' bo'ladi va ularni butunlay tashlash tanlanmani juda
    | kichraytiradi. Muharrir tasdig'i mexanizmi qo'shilgach, buni .env orqali
    | faqat ['used'] ga qat'iylashtirish mumkin.
    |
    */
    'included_conditions' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MARKET_STATS_INCLUDED_CONDITIONS', 'used,unknown')),
    ))),
);
AVTONARX_EOF_9271
echo '  ✓ config/market_statistics.php'

mkdir -p "$(dirname 'app/Models/MarketListing.php')"
cat > 'app/Models/MarketListing.php' << 'AVTONARX_EOF_9271'
<?php

namespace App\Models;

use App\Enums\ConditionType;
use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Enums\NormalizationStatus;
use App\Enums\SellerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketListing extends Model
{
    protected $fillable = array(
        'source_id',
        'external_id',
        'canonical_url',
        'brand_raw',
        'model_raw',
        'brand_id',
        'model_id',
        'normalization_status',
        'normalization_confidence',
        'year',
        'price_amount',
        'currency',
        'price_uzs',
        'exchange_rate_id',
        'condition',
        'seller_type',
        'region',
        'city',
        'status',
        'content_hash',
        'source_published_at',
        'first_seen_at',
        'last_seen_at',
        'missing_runs',
    );

    protected $casts = array(
        'currency' => Currency::class,
        'condition' => ConditionType::class,
        'seller_type' => SellerType::class,
        'status' => ListingStatus::class,
        'normalization_status' => NormalizationStatus::class,
        'normalization_confidence' => 'float',
        'year' => 'integer',
        'price_amount' => 'integer',
        'price_uzs' => 'integer',
        'missing_runs' => 'integer',
        'source_published_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    );

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(ListingPriceSnapshot::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', ListingStatus::Active->value);
    }

    public function scopeMatched($query)
    {
        return $query->where('normalization_status', NormalizationStatus::Matched->value);
    }

    /**
     * TZ 11-bo'lim: tanlanmaga "last_seen_at 72 soatdan eski bo'lmagan"
     * e'lonlar kiradi. Bu scope statistikani hisoblashda BEVOSITA freshness
     * cheklovini qo'llaydi (ExpireStaleListingsJob'ga tayanib qolmasdan).
     */
    public function scopeFreshForStatistics($query)
    {
        $hours = (int) config('market_statistics.sample_max_age_hours', 72);

        return $query->where('last_seen_at', '>=', now()->subHours($hours));
    }

    /**
     * TZ 11-bo'lim: tanlanma — "used yoki tasdiqlangan unknown". Yangi ('new')
     * mashinalar ikkilamchi bozor medianasiga kirmasligi kerak (ular
     * official_offers'ga tegishli). Kiritiladigan condition'lar ro'yxati
     * config('market_statistics.included_conditions') orqali sozlanadi.
     */
    public function scopeSecondaryMarket($query)
    {
        $conditions = config('market_statistics.included_conditions', array('used', 'unknown'));

        return $query->whereIn('condition', $conditions);
    }
}
AVTONARX_EOF_9271
echo '  ✓ app/Models/MarketListing.php'

mkdir -p "$(dirname 'app/Services/PriceStatistics/MarketStatisticsService.php')"
cat > 'app/Services/PriceStatistics/MarketStatisticsService.php' << 'AVTONARX_EOF_9271'
<?php

namespace App\Services\PriceStatistics;

use App\Enums\Currency;
use App\Models\MarketListing;
use App\Models\MarketPriceStatistic;

class MarketStatisticsService
{
    public const MIN_SAMPLE_SIZE = 10;
    private const METHOD_VERSION = 'v1';

    private MedianCalculator $calculator;

    public function __construct(MedianCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function recalculateAll(): int
    {
        $updated = 0;

        // 1) Butun O'zbekiston bo'yicha (region_code = null) — avvalgidek,
        // eng keng ko'rinish, har doim mavjud bo'lishi kerak (agar namuna
        // yetarli bo'lsa).
        $groups = MarketListing::query()
            ->active()
            ->matched()
            ->freshForStatistics()
            ->secondaryMarket()
            ->select('brand_id', 'model_id', 'year')
            ->groupBy('brand_id', 'model_id', 'year')
            ->get();

        foreach ($groups as $group) {
            $this->recalculateGroup($group->brand_id, $group->model_id, $group->year, null);
            $updated++;
        }

        // 2) Har bir hudud bo'yicha ALOHIDA — foydalanuvchi aynan qaysi
        // hududda narx qanday ekanini bilishi uchun. Faqat 'region'
        // maydoni to'ldirilgan e'lonlar hisobga olinadi (parser undan
        // OLX'ning "joylashuv" qatoridan oladi).
        $regionGroups = MarketListing::query()
            ->active()
            ->matched()
            ->freshForStatistics()
            ->secondaryMarket()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->select('brand_id', 'model_id', 'year', 'region')
            ->groupBy('brand_id', 'model_id', 'year', 'region')
            ->get();

        foreach ($regionGroups as $group) {
            $result = $this->recalculateGroup($group->brand_id, $group->model_id, $group->year, $group->region);

            if ($result !== null) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Berilgan guruh uchun hozirgi (matched+active) e'lonlar sonini qaytaradi.
     * Bu son sample_size talabini qanoatlantirmasa ham hisoblanadi —
     * Public API "insufficient_sample" sababini shu orqali ko'rsatadi.
     */
    public function countAvailableListings(int $brandId, int $modelId, ?int $year, ?string $regionCode = null): int
    {
        $query = MarketListing::query()
            ->active()
            ->matched()
            ->freshForStatistics()
            ->secondaryMarket()
            ->where('brand_id', $brandId)
            ->where('model_id', $modelId);

        if ($year !== null) {
            $query->where('year', $year);
        } else {
            $query->whereNull('year');
        }

        if ($regionCode !== null) {
            $query->where('region', $regionCode);
        }

        return $query->count();
    }

    /**
     * TZ 15-bo'lim: "model uchun qayta hisoblashda Redis lock". Aynan bir
     * guruh (brand+model+year+region) bir vaqtning o'zida ikki joydan
     * (rejalashtirilgan recalc va admin "majburiy qayta hisoblash") qayta
     * hisoblanib, poyga (race) holatiga tushmasligi uchun har bir guruh
     * alohida lock ostida hisoblanadi. Lock band bo'lsa — boshqa jarayon shu
     * guruhni allaqachon hisoblayapti, takrorlash keraksiz, null qaytariladi.
     */
    public function recalculateGroup(int $brandId, int $modelId, ?int $year, ?string $regionCode = null): ?MarketPriceStatistic
    {
        $lockKey = sprintf(
            'market-stats-recalc:%d:%d:%s:%s',
            $brandId,
            $modelId,
            $year ?? 'null',
            $regionCode ?? 'null',
        );

        $result = \Illuminate\Support\Facades\Cache::lock($lockKey, 30)->get(
            fn () => $this->computeGroup($brandId, $modelId, $year, $regionCode),
        );

        // get() lock band bo'lsa false qaytaradi; closure null qaytarsa (namuna
        // yetarli emas holatida) null qaytaradi. null !== false, shuning uchun
        // ikki holat aniq farqlanadi.
        return $result === false ? null : $result;
    }

    private function computeGroup(int $brandId, int $modelId, ?int $year, ?string $regionCode = null): ?MarketPriceStatistic
    {
        $query = MarketListing::query()
            ->active()
            ->matched()
            ->freshForStatistics()
            ->secondaryMarket()
            ->where('brand_id', $brandId)
            ->where('model_id', $modelId);

        if ($year !== null) {
            $query->where('year', $year);
        } else {
            $query->whereNull('year');
        }

        if ($regionCode !== null) {
            $query->where('region', $regionCode);
        }

        $earliestListing = (clone $query)->orderBy('first_seen_at')->first();
        $latestListing = (clone $query)->orderByDesc('last_seen_at')->first();

        // MUHIM TUZATISH: avvalgi versiya pluck('price_uzs')->filter() dan
        // keyin, faqat natija BUTUNLAY bo'sh bo'lsa price_amount'ga
        // qaytardi ("hammasi yoki hech narsa" mantig'i). Bu — agar hatto
        // bitta yozuvda price_uzs to'ldirilgan bo'lsa, price_uzs'i hali
        // yo'q qolgan barcha boshqa (aslida yaroqli) yozuvlarni tashlab
        // yuborar edi. Endi HAR BIR qatorni alohida tekshiramiz.
        $rows = (clone $query)->get(array('price_uzs', 'price_amount', 'currency'));

        $prices = array();
        foreach ($rows as $row) {
            if ($row->price_uzs !== null) {
                $prices[] = (int) $row->price_uzs;

                continue;
            }

            // price_uzs hali to'ldirilmagan (masalan konvertatsiya joby
            // hali ishlamagan) — lekin valyuta UZS bo'lsa, price_amount
            // to'g'ridan-to'g'ri ishlatilaveradi (konvertatsiya shart emas).
            // MUHIM: MarketListing modelida 'currency' => Currency::class
            // (native PHP backed enum) cast qilingan, shuning uchun
            // $row->currency oddiy satr emas, Currency enum obyekti.
            // Qattiq solishtiruv uchun Currency::UZS bilan solishtiramiz,
            // 'UZS' satri bilan emas.
            if ($row->currency === Currency::UZS) {
                $prices[] = (int) $row->price_amount;
            }

            // Boshqa valyuta va price_uzs hali yo'q bo'lsa — bu qatorni
            // statistikaga qo'shmaymiz (noaniq narx bilan hisoblab bo'lmaydi).
        }

        $sampleSizeBeforeFilter = sizeof($prices);
        $minSampleSize = (int) config('market_statistics.min_sample_size', self::MIN_SAMPLE_SIZE);

        if ($sampleSizeBeforeFilter < $minSampleSize) {
            // Tanlanma yetarli emas — statistika yaratilmaydi/yangilanmaydi.
            // Agar avval yaratilgan bo'lsa, uni ham o'chiramiz (endi ko'rsatilmasligi kerak).
            MarketPriceStatistic::query()
                ->where('brand_id', $brandId)
                ->where('model_id', $modelId)
                ->where('year', $year)
                ->when(
                    $regionCode === null,
                    fn ($q) => $q->whereNull('region_code'),
                    fn ($q) => $q->where('region_code', $regionCode),
                )
                ->delete();

            return null;
        }

        $cleanPrices = $this->calculator->filterOutliers(
            $prices,
            (int) config('market_statistics.global_min_price_uzs'),
            (int) config('market_statistics.global_max_price_uzs'),
            (int) config('market_statistics.iqr_min_sample_size'),
        );
        $excludedCount = $sampleSizeBeforeFilter - sizeof($cleanPrices);

        if (sizeof($cleanPrices) === 0) {
            // Global chegaralardan o'tgan birorta ham narx qolmadi.
            MarketPriceStatistic::query()
                ->where('brand_id', $brandId)
                ->where('model_id', $modelId)
                ->where('year', $year)
                ->when(
                    $regionCode === null,
                    fn ($q) => $q->whereNull('region_code'),
                    fn ($q) => $q->where('region_code', $regionCode),
                )
                ->delete();

            return null;
        }

        $stats = $this->calculator->calculate($cleanPrices);

        return MarketPriceStatistic::updateOrCreate(
            array(
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'year' => $year,
                'region_code' => $regionCode,
            ),
            array(
                'currency' => 'UZS',
                'sample_size' => sizeof($cleanPrices),
                'excluded_count' => $excludedCount,
                'median_price_uzs' => $stats['median'],
                'mean_price_uzs' => $stats['mean'],
                'min_price_uzs' => $stats['min'],
                'max_price_uzs' => $stats['max'],
                'p25_price_uzs' => $stats['p25'],
                'p75_price_uzs' => $stats['p75'],
                'period_from' => $earliestListing ? $earliestListing->first_seen_at : null,
                'period_to' => $latestListing ? $latestListing->last_seen_at : null,
                'method_version' => self::METHOD_VERSION,
                'calculated_at' => now(),
            )
        );
    }
}
AVTONARX_EOF_9271
echo '  ✓ app/Services/PriceStatistics/MarketStatisticsService.php'

mkdir -p "$(dirname 'routes/console.php')"
cat > 'routes/console.php' << 'AVTONARX_EOF_9271'
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\DiscoverOlxBrandsJob;
use App\Jobs\DiscoverOlxModelsJob;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\RecalculateStatisticsJob;
use App\Jobs\RunParserSourceJob;
use App\Jobs\ExpireOfficialOffersJob;
use App\Jobs\ExpireStaleListingsJob;
use App\Jobs\FetchExchangeRatesJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DiscoverOlxBrandsJob())->dailyAt('21:00');
Schedule::job(new DiscoverOlxModelsJob())->dailyAt('21:15');
Schedule::job(new RunParserSourceJob('olx_uz'))->dailyAt('23:00');
Schedule::job(new ExpireStaleListingsJob())->hourly();
Schedule::job(new ExpireOfficialOffersJob())->hourly();
// TZ 11-bo'lim: agregatlar "kamida soatiga bir marta" qayta hisoblanishi shart
// (TZ 17: "agregatlar 3 soatdan eski" — alert sharti). Shu sabab dailyAt emas,
// hourly. Bir vaqtda ikkita recalc ishlamasligi uchun jobning o'zida
// WithoutOverlapping middleware bor.
Schedule::job(new RecalculateStatisticsJob())->hourly()->withoutOverlapping();
Schedule::job(new FetchExchangeRatesJob())->dailyAt('08:00');
AVTONARX_EOF_9271
echo '  ✓ routes/console.php'

mkdir -p "$(dirname 'app/Jobs/RecalculateStatisticsJob.php')"
cat > 'app/Jobs/RecalculateStatisticsJob.php' << 'AVTONARX_EOF_9271'
<?php

namespace App\Jobs;

use App\Services\PriceStatistics\MarketStatisticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateStatisticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    /**
     * TZ 15-bo'lim: qayta hisoblash bir vaqtda ikki marta ishlamasligi kerak.
     * WithoutOverlapping Redis (cache) lock orqali butun recalc jarayonini
     * qulflaydi. expireAfter — job osilib qolsa lock'ni avtomatik bo'shatadi.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return array(
            (new WithoutOverlapping('recalculate-market-statistics'))
                ->expireAfter(700)
                ->dontRelease(),
        );
    }

    public function handle(MarketStatisticsService $statisticsService): void
    {
        $count = $statisticsService->recalculateAll();

        Log::info("RecalculateStatisticsJob tugadi: {$count} ta guruh qayta hisoblandi.");
    }
}
AVTONARX_EOF_9271
echo '  ✓ app/Jobs/RecalculateStatisticsJob.php'

mkdir -p "$(dirname 'tests/Feature/PriceStatistics/MarketStatisticsServiceTest.php')"
cat > 'tests/Feature/PriceStatistics/MarketStatisticsServiceTest.php' << 'AVTONARX_EOF_9271'
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

it('excludes a clear outlier via IQR filtering once sample_size reaches the IQR threshold (TZ: 20)', function () {
    // 20 ta baravar taqsimlangan qiymat (100M dan 290M gacha, 10M qadam bilan).
    $amounts = array();
    for ($i = 0; $i < 20; $i++) {
        $amounts[] = 100_000_000 + ($i * 10_000_000);
    }

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2026, array(
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ));
    }

    // Aniq outlier — jami tanlanma 21 taga yetadi, ya'ni IQR chegarasi (20) ga yetadi/oshadi.
    makeMatchedListing($this->brand->id, $this->model->id, 2026, array(
        'price_amount' => 900_000_000,
        'price_uzs' => 900_000_000,
    ));

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2026);

    expect($stat)->not->toBeNull();
    expect($stat->sample_size)->toBe(20); // outlier IQR orqali chiqarib tashlangach
    expect($stat->excluded_count)->toBe(1);
    expect($stat->median_price_uzs)->toBe(195_000_000);
    expect($stat->max_price_uzs)->toBe(290_000_000); // 900M statistikaga kirmagan
});

it('does NOT apply IQR when sample_size is below the IQR threshold — only global bounds apply (TZ 11-bo\'lim, 4-bosqich)', function () {
    // 10 ta oddiy narx + 1 ta juda katta narx — jami 11 ta, IQR chegarasi (20) dan kam.
    $amounts = array(100_000_000, 110_000_000, 120_000_000, 130_000_000, 140_000_000, 150_000_000, 160_000_000, 170_000_000, 180_000_000, 190_000_000);

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2027, array(
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ));
    }

    makeMatchedListing($this->brand->id, $this->model->id, 2027, array(
        'price_amount' => 900_000_000,
        'price_uzs' => 900_000_000,
    ));

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2027);

    expect($stat)->not->toBeNull();
    // Tanlanma (11) IQR chegarasidan (20) kam bo'lgani uchun 900M chiqarib tashlanmaydi —
    // u global chegaralar (standart: 3M-2B so'm) ichida qoladi.
    expect($stat->sample_size)->toBe(11);
    expect($stat->excluded_count)->toBe(0);
    expect($stat->max_price_uzs)->toBe(900_000_000);
});

it('excludes prices outside the configured global bounds regardless of sample size (TZ 11-bo\'lim, 1-2-bosqich)', function () {
    $amounts = array(100_000_000, 110_000_000, 120_000_000, 130_000_000, 140_000_000, 150_000_000, 160_000_000, 170_000_000, 180_000_000, 190_000_000);

    foreach ($amounts as $amount) {
        makeMatchedListing($this->brand->id, $this->model->id, 2028, array(
            'price_amount' => $amount,
            'price_uzs' => $amount,
        ));
    }

    // "Aniq to'liqsiz" narx — global minimumdan (standart 3M so'm) past.
    makeMatchedListing($this->brand->id, $this->model->id, 2028, array(
        'price_amount' => 500_000,
        'price_uzs' => 500_000,
    ));

    // Realistik bo'lmagan haddan tashqari katta narx — global maksimumdan (standart 2B so'm) yuqori.
    makeMatchedListing($this->brand->id, $this->model->id, 2028, array(
        'price_amount' => 3_000_000_000,
        'price_uzs' => 3_000_000_000,
    ));

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
        makeMatchedListing($this->brand->id, $this->model->id, 2020, array(
            'price_amount' => 100_000_000,
            'price_uzs' => null,
        ));
    }

    $count = $this->service->countAvailableListings($this->brand->id, $this->model->id, 2020);

    expect($count)->toBe(4);
});

it('excludes NEW-condition listings from the secondary-market sample (TZ 11-bo\'lim)', function () {
    // 9 ta haqiqiy "used" e'lon.
    for ($i = 0; $i < 9; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2019, array(
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
            'condition' => 'used',
        ));
    }

    // 5 ta YANGI ('new') mashina — ular official_offers'ga tegishli va
    // ikkilamchi bozor medianasiga umuman kirmasligi kerak.
    for ($i = 0; $i < 5; $i++) {
        makeMatchedListing($this->brand->id, $this->model->id, 2019, array(
            'price_amount' => 200_000_000,
            'price_uzs' => 200_000_000,
            'condition' => 'new',
        ));
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
    $listings = array();
    for ($i = 0; $i < 10; $i++) {
        $listings[] = makeMatchedListing($this->brand->id, $this->model->id, 2018, array(
            'price_amount' => 100_000_000,
            'price_uzs' => 100_000_000,
            'last_seen_at' => now(),
        ));
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2018);
    expect($stat)->not->toBeNull();
    expect($stat->sample_size)->toBe(10);

    // Endi 3 ta yozuvni 72 soatdan eski qilamiz — ular hali "active" bo'lsa
    // ham (ExpireStaleListingsJob hali ishlamagan bo'lishi mumkin),
    // statistikaga kirmasligi kerak. UPDATE...LIMIT ishlatmaymiz (Postgres
    // uni qo'llab-quvvatlamaydi) — aniq ID'lar bo'yicha yangilaymiz.
    foreach (array_slice($listings, 0, 3) as $listing) {
        $listing->update(array('last_seen_at' => now()->subHours(80)));
    }

    $stat = $this->service->recalculateGroup($this->brand->id, $this->model->id, 2018);

    // 3 tasi eskirgan → 7 ta qoladi → MIN_SAMPLE_SIZE (10) dan kam → null.
    expect($stat)->toBeNull();
});
AVTONARX_EOF_9271
echo '  ✓ tests/Feature/PriceStatistics/MarketStatisticsServiceTest.php'

echo ""
echo "Tayyor. Endi testlarni ishga tushiring:"
echo "  php artisan test --filter=MarketStatistics"
