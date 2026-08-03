<?php

namespace App\Services\MarketListings;

use App\DTO\ListingData;
use App\Enums\EntityType;
use App\Exceptions\SuspiciousListingRejectedException;
use App\Models\ListingPriceSnapshot;
use App\Models\MarketListing;
use App\Services\Catalog\CatalogAliasService;
use App\Services\ExchangeRates\ExchangeRateService;

class ListingIngestionService
{
    /**
     * TZ bo'lim 12: "Snapshot" rejimida ikkita muvaffaqiyatli to'liq
     * ko'rib chiqishda topilmasa (missing_runs >= 2) — e'lon inactive bo'ladi.
     */
    private const MAX_MISSING_RUNS = 2;

    public function __construct(
        private readonly CatalogAliasService $aliasService,
        private readonly ExchangeRateService $exchangeRateService,
        private readonly ListingSanityChecker $sanityChecker,
    ) {
    }

    public function ingest(ListingData $data): MarketListing
    {
        $contentHash = $data->computeContentHash();

        $listing = MarketListing::query()
            ->where('source_id', $data->sourceId)
            ->where('external_id', $data->externalId)
            ->first();

        // Agar parser (masalan OlxUzAdapter) ParserTarget orqali qaysi
        // brand/model ekanini ALLAQACHON aniq bilsa — shu ID'lar to'g'ridan-to'g'ri
        // ishlatiladi, alias jadvali orqali nomga qarab qayta izlanmaydi. Bu —
        // brand/model nomi keyinchalik (admin tomonidan) o'zgartirilsa ham
        // moslikning buzilmasligini kafolatlaydi, chunki target'ning o'zi
        // "haqiqat manbai" hisoblanadi.
        //
        // Agar bu ID'lar berilmagan bo'lsa (masalan tashqi HTTP Ingestion API
        // orqali kelgan, parser bizning ichki ID'larimizni bilmaydigan holat) —
        // avvalgidek brandRaw/modelRaw nomi orqali alias jadvalida izlanadi.
        if ($data->knownBrandId !== null && $data->knownModelId !== null) {
            $brandId = $data->knownBrandId;
            $modelId = $data->knownModelId;
        } else {
            $brandId = $data->brandRaw
                ? $this->aliasService->resolve(EntityType::Brand, $data->brandRaw, $data->sourceId)
                : null;

            $modelId = $data->modelRaw
                ? $this->aliasService->resolve(EntityType::Model, $data->modelRaw, $data->sourceId)
                : null;
        }

        $normalizationStatus = ($brandId && $modelId) ? 'matched' : 'pending';

        $priceUzs = $this->exchangeRateService->convertToUzs($data->priceAmount, $data->currency);

        // Himoya qatlami (TZ: "aniq bo'lmasa, olinmasin"): bu yerga qaysi yo'l
        // orqali kelishidan qat'i nazar (ichki scraper yoki tashqi HTTP
        // ingestion API) — OLX fallback natijalari yoki mashina uchun aqlga
        // sig'maydigan narxdagi elementlar bazaga UMUMAN yozilmaydi.
        // priceUzs hisoblab bo'lmagan holatda (masalan kurs topilmadi) narx
        // tekshiruvi o'tkazib yuboriladi — bu boshqa (currency) xatosi sifatida
        // yuqorida allaqachon ko'rib chiqiladi.
        $suspiciousReason = $this->sanityChecker->check($data->canonicalUrl, $priceUzs);

        if ($suspiciousReason !== null) {
            throw new SuspiciousListingRejectedException($suspiciousReason['code'], $suspiciousReason['message']);
        }

        $attributes = array(
            'canonical_url' => $data->canonicalUrl,
            'brand_raw' => $data->brandRaw,
            'model_raw' => $data->modelRaw,
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'normalization_status' => $normalizationStatus,
            'year' => $data->year,
            'price_amount' => $data->priceAmount,
            'currency' => $data->currency,
            'price_uzs' => $priceUzs,
            'condition' => $data->condition,
            'seller_type' => $data->sellerType,
            'region' => $data->region,
            'city' => $data->city,
            // E'lon qayta ko'rilganda har doim qayta "active" qilinadi — agar u
            // avval missing_runs sabab inactive bo'lib qolgan bo'lsa-yu, keyingi
            // safar sahifada yana paydo bo'lsa (masalan e'lon vaqtincha
            // ko'rinmay qolgan bo'lsa), tabiiy ravishda qaytadan faollashadi.
            'status' => 'active',
            'content_hash' => $contentHash,
            'source_published_at' => $data->sourcePublishedAt,
            'last_seen_at' => now(),
        );

        if ($listing === null) {
            $listing = MarketListing::create(array_merge($attributes, array(
                'source_id' => $data->sourceId,
                'external_id' => $data->externalId,
                'first_seen_at' => now(),
                'missing_runs' => 0,
            )));

            $this->recordSnapshot($listing, $contentHash);

            return $listing;
        }

        if ($listing->content_hash === $contentHash) {
            $listing->update(array('last_seen_at' => now(), 'missing_runs' => 0, 'status' => 'active'));

            return $listing;
        }

        $listing->update(array_merge($attributes, array('missing_runs' => 0)));
        $this->recordSnapshot($listing, $contentHash);

        return $listing->refresh();
    }

    /**
     * TZ bo'lim 12 ("Snapshot"): bitta target (brend+model sahifasi) to'liq va
     * muvaffaqiyatli qayta ko'rib chiqilgandan keyin chaqiriladi. Shu safar
     * sahifada KO'RILMAGAN, lekin bazada hali "active" turgan e'lonlarning
     * missing_runs sonini +1 qiladi, MAX_MISSING_RUNS'ga yetganda inactive
     * qiladi.
     *
     * Faqat target run TO'LIQ muvaffaqiyatli tugaganda chaqirilishi kerak —
     * xato/bloklangan/qisman natijalarda hech narsani deaktivatsiya qilmaslik
     * kerak (TZ: "failed yoki partial batch yozuvlarni deaktivatsiya qilmaydi").
     *
     * @param  array<int, string>  $seenExternalIds  Shu safar sahifada haqiqatan
     *                                                topilgan e'lonlarning external_id'lari.
     */
    public function markMissingForModel(int $sourceId, int $modelId, array $seenExternalIds): void
    {
        $query = MarketListing::query()
            ->where('source_id', $sourceId)
            ->where('model_id', $modelId)
            ->where('status', 'active');

        if (! empty($seenExternalIds)) {
            $query->whereNotIn('external_id', $seenExternalIds);
        }

        $missingListings = $query->get();

        foreach ($missingListings as $listing) {
            $newMissingRuns = $listing->missing_runs + 1;

            $listing->update(array(
                'missing_runs' => $newMissingRuns,
                'status' => $newMissingRuns >= self::MAX_MISSING_RUNS ? 'inactive' : $listing->status,
            ));
        }
    }

    private function recordSnapshot(MarketListing $listing, string $contentHash): void
    {
        ListingPriceSnapshot::create(array(
            'market_listing_id' => $listing->id,
            'price_amount' => $listing->price_amount,
            'currency' => $listing->currency,
            'price_uzs' => $listing->price_uzs,
            'observed_at' => now(),
            'content_hash' => $contentHash,
        ));
    }
}
