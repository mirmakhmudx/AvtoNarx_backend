<?php

namespace App\Services\OfficialOffers;

use App\DTO\OfficialOfferData;
use App\Enums\EntityType;
use App\Exceptions\UnmatchedCatalogEntityException;
use App\Models\OfficialOffer;
use App\Models\Source;
use App\Services\Catalog\CatalogAliasService;
use App\Services\ExchangeRates\ExchangeRateService;
class OfficialOfferIngestionService
{
    public function __construct(
        private readonly CatalogAliasService $aliasService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function ingest(OfficialOfferData $data): OfficialOffer
    {
        $source = Source::findOrFail($data->sourceId);

        $brandId = $data->brandRaw
            ? $this->aliasService->resolve(EntityType::Brand, $data->brandRaw, $data->sourceId)
            : null;

        if ($brandId === null) {
            throw new UnmatchedCatalogEntityException('unmatched_brand', "Brend topilmadi: {$data->brandRaw}");
        }

        $modelId = $data->modelRaw
            ? $this->aliasService->resolve(EntityType::Model, $data->modelRaw, $data->sourceId)
            : null;

        if ($modelId === null) {
            throw new UnmatchedCatalogEntityException('unmatched_model', "Model topilmadi: {$data->modelRaw}");
        }

        $priceUzs = $this->exchangeRateService->convertToUzs($data->priceAmount, $data->currency);

        $existing = OfficialOffer::query()
            ->where('source_id', $data->sourceId)
            ->where('model_id', $modelId)
            ->where('trim_name', $data->trimName)
            ->first();

        $priceUnchanged = $existing !== null && $existing->content_hash === $data->contentHash;

        $attributes = [
            'source_id' => $data->sourceId,
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'trim_name' => $data->trimName,
            'year' => $data->year,
            'price_amount' => $data->priceAmount,
            'currency' => $data->currency,
            'price_uzs' => $priceUzs,
            'source_url' => $data->sourceUrl,
            'external_id' => $data->externalId,
            'valid_from' => $data->validFrom,
            'valid_to' => $data->validTo,
            'observed_at' => $data->observedAt,
            'content_hash' => $data->contentHash,
        ];

        if ($priceUnchanged) {
            $existing->update([
                'observed_at' => $data->observedAt,
                'valid_from' => $data->validFrom,
                'valid_to' => $data->validTo,
            ]);

            return $existing->refresh();
        }
        $autoPublish = (bool) ($source->settings['auto_publish'] ?? false);

        if ($autoPublish) {
            $attributes['publication_status'] = 'published';
            $attributes['published_at'] = now();
            $attributes['verified_at'] = now();
            $attributes['verified_by'] = null; // tizim tomonidan avtomatik, odam emas
        } else {
            $attributes['publication_status'] = 'pending';
        }

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        return OfficialOffer::create($attributes);
    }
}
