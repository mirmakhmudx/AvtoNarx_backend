<?php

namespace App\Services\OfficialOffers;

use App\DTO\OfficialOfferData;
use App\Enums\EntityType;
use App\Exceptions\UnmatchedCatalogEntityException;
use App\Models\OfficialOffer;
use App\Models\Source;
use App\Services\Catalog\CatalogAliasService;
use App\Services\ExchangeRates\ExchangeRateService;

/**
 * TZ bo'lim 8.3: parserdan kelgan rasmiy narx yozuvlarini qabul qiladi.
 *
 * market_listings'dan farqi: official_offers.brand_id/model_id NOT NULL
 * (migratsiyada nullable emas) — shuning uchun bu yerda "pending
 * normalization" degan holat yo'q. Agar brend yoki model bizning
 * katalogimizda topilmasa, item butunlay rad etiladi (unmatched_brand /
 * unmatched_model), listing'lardagidek "keyinroq admin bog'laydi" degan
 * yumshoq yo'l yo'q.
 */
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
            // Narx o'zgarmagan — faqat "yana kuzatildi" faktini yozamiz,
            // moderatsiya holatini qayta ochmaymiz (TZ: faqat YANGI yoki
            // O'ZGARGAN narx pending bo'ladi).
            $existing->update([
                'observed_at' => $data->observedAt,
                'valid_from' => $data->validFrom,
                'valid_to' => $data->validTo,
            ]);

            return $existing->refresh();
        }

        // TZ bo'lim 8.3: "Avtomatik nashr etish faqat alohida ruxsatga ega
        // manba uchun mumkin." Bu ruxsat sources.settings->auto_publish
        // bayrog'i orqali beriladi (admin tomonidan qo'lda yoqiladi).
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
