<?php

namespace App\Services\MarketListings;

use App\DTO\ListingData;
use App\Enums\EntityType;
use App\Exceptions\SuspiciousListingRejectedException;
use App\Models\ListingPriceSnapshot;
use App\Models\MarketListing;
use App\Services\Catalog\CatalogAliasService;
use App\Services\ExchangeRates\ExchangeRateService;
use Illuminate\Support\Facades\DB;

class ListingIngestionService
{

    private const MAX_MISSING_RUNS = 2;

    public function __construct(
        private readonly CatalogAliasService $aliasService,
        private readonly ExchangeRateService $exchangeRateService,
        private readonly ListingSanityChecker $sanityChecker,
    ) {}

    public function ingest(ListingData $data): MarketListing
    {
        // TZ 19: parser bergan content_hash (ContentHashBuilder) ishlatiladi;
        // faqat berilmagan holatda backend o'zi hisoblaydi (zaxira).
        $contentHash = $data->contentHash ?? $data->computeContentHash();

        $listing = MarketListing::query()
            ->where('source_id', $data->sourceId)
            ->where('external_id', $data->externalId)
            ->first();

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

        // TZ: UZS bo'lmagan valyutada kurs topilmasa, narxni UZS'ga aylantirib
        // bo'lmaydi — element jimgina qabul qilinmaydi, balki rad etiladi.
        if ($data->currency !== 'UZS' && $priceUzs === null) {
            throw new SuspiciousListingRejectedException(
                'currency_conversion_failed',
                "Valyuta '{$data->currency}' uchun kurs topilmadi — narxni UZS'ga aylantirib bo'lmadi.",
            );
        }

        // Himoya qatlami (TZ: "aniq bo'lmasa, olinmasin").
        $suspiciousReason = $this->sanityChecker->check($data->canonicalUrl, $priceUzs);

        if ($suspiciousReason !== null) {
            throw new SuspiciousListingRejectedException($suspiciousReason['code'], $suspiciousReason['message']);
        }

        $attributes = [
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
            'status' => 'active',
            'content_hash' => $contentHash,
            'source_published_at' => $data->sourcePublishedAt,
            'last_seen_at' => now(),
        ];

        // TZ: e'lon yozuvi va uning snapshot'i bitta tranzaksiyada — biri
        // saqlanib, ikkinchisi yiqilib qolmasligi uchun (mustahkamlik).
        return DB::transaction(function () use ($listing, $attributes, $contentHash, $data) {
            if ($listing === null) {
                $listing = MarketListing::create(array_merge($attributes, [
                    'source_id' => $data->sourceId,
                    'external_id' => $data->externalId,
                    'first_seen_at' => now(),
                    'missing_runs' => 0,
                ]));

                $this->recordSnapshot($listing, $contentHash);

                return $listing;
            }

            if ($listing->content_hash === $contentHash) {
                $listing->update(['last_seen_at' => now(), 'missing_runs' => 0, 'status' => 'active']);

                return $listing;
            }

            $listing->update(array_merge($attributes, ['missing_runs' => 0]));
            $this->recordSnapshot($listing, $contentHash);

            return $listing->refresh();
        });
    }

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

            $listing->update([
                'missing_runs' => $newMissingRuns,
                'status' => $newMissingRuns >= self::MAX_MISSING_RUNS ? 'inactive' : $listing->status,
            ]);
        }
    }

    private function recordSnapshot(MarketListing $listing, string $contentHash): void
    {
        ListingPriceSnapshot::create([
            'market_listing_id' => $listing->id,
            'price_amount' => $listing->price_amount,
            'currency' => $listing->currency,
            'price_uzs' => $listing->price_uzs,
            'observed_at' => now(),
            'content_hash' => $contentHash,
        ]);
    }
}
