<?php

namespace App\Services\MarketListings;

use App\DTO\ListingData;
use App\Enums\EntityType;
use App\Models\ListingPriceSnapshot;
use App\Models\MarketListing;
use App\Services\Catalog\CatalogAliasService;
use App\Services\ExchangeRates\ExchangeRateService;

class ListingIngestionService
{
    public function __construct(
        private readonly CatalogAliasService $aliasService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {
    }

    public function ingest(ListingData $data): MarketListing
    {
        $contentHash = $data->computeContentHash();

        $listing = MarketListing::query()
            ->where('source_id', $data->sourceId)
            ->where('external_id', $data->externalId)
            ->first();

        $brandId = $data->brandRaw
            ? $this->aliasService->resolve(EntityType::Brand, $data->brandRaw, $data->sourceId)
            : null;

        $modelId = $data->modelRaw
            ? $this->aliasService->resolve(EntityType::Model, $data->modelRaw, $data->sourceId)
            : null;

        $normalizationStatus = ($brandId && $modelId) ? 'matched' : 'pending';

        $priceUzs = $this->exchangeRateService->convertToUzs($data->priceAmount, $data->currency);

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
            $listing->update(array('last_seen_at' => now(), 'missing_runs' => 0));

            return $listing;
        }

        $listing->update(array_merge($attributes, array('missing_runs' => 0)));
        $this->recordSnapshot($listing, $contentHash);

        return $listing->refresh();
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
