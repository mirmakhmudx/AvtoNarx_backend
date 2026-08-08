<?php

namespace App\Services\Parser\Adapters;

use App\DTO\OfficialOfferData;
use App\Models\Source;
use App\Services\Parser\Extraction\ContentHashBuilder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TZ (Parser) "Этап 3. Official adapter — Uzum Avto → official_offers".
 * Uzum Avto rasmiy narxlarini oladi va OfficialOfferData ko'rinishiga o'tkazadi.
 *
 * webview.uzumavto.uz — ilova ichidagi webview JSON API. Endpoint va struktura
 * manba sozlamalaridan olinadi (kod o'zgartirmasdan moslash mumkin):
 *   settings.catalog_endpoint  — to'liq URL
 *   settings.json_path         — modellar ro'yxati JSON ichida qayerda ("data.models")
 */
class UzumAvtoAdapter
{
    private const SOURCE_CODE = 'uzum_avto';

    private const CONDITION = 'new';

    private const DEFAULT_CURRENCY = 'UZS';

    private const REQUEST_TIMEOUT = 30;

    public function __construct(
        private readonly ContentHashBuilder $contentHashBuilder,
    ) {}

    /** @return array<string, mixed> */
    public function fetchRaw(Source $source, ?string $overrideUrl = null): array
    {
        $endpoint = $overrideUrl ?? ($source->settings['catalog_endpoint'] ?? null);

        if (! $endpoint) {
            throw new RuntimeException(
                "Uzum endpoint aniqlanmagan. Manba sozlamalarida 'catalog_endpoint' ni bering yoki --url bilan uzating."
            );
        }

        $response = Http::timeout(self::REQUEST_TIMEOUT)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'AvtoNarx/1.0 (+official-price-collector)',
            ])
            ->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException("Uzum so'rovi muvaffaqiyatsiz: HTTP {$response->status()}");
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Uzum javobi JSON emas yoki bo\'sh.');
        }

        return $json;
    }

    /** @return array<int, OfficialOfferData> */
    public function fetchOfficialOffers(Source $source, ?string $overrideUrl = null): array
    {
        return $this->mapCatalog($this->fetchRaw($source, $overrideUrl), $source);
    }

    /**
     * ⚠️ MOSLASH KERAK BO'LGAN YAGONA JOY — Uzum JSON'ini OfficialOfferData'ga
     * o'tkazadi. Haqiqiy JSON boshqacha bo'lsa, faqat shu metod ichini
     * o'zgartiring; qolgan hamma narsa o'zgarishsiz ishlaydi.
     *
     * @param  array<string, mixed>  $json
     * @return array<int, OfficialOfferData>
     */
    public function mapCatalog(array $json, Source $source): array
    {
        $models = $this->extractModelList($json, $source);
        $offers = [];
        $observedAt = new \DateTimeImmutable('now');

        foreach ($models as $model) {
            if (! is_array($model)) {
                continue;
            }

            $brand = (string) ($model['brand'] ?? $model['brand_name'] ?? 'Chevrolet');
            $modelName = (string) ($model['name'] ?? $model['model'] ?? $model['title'] ?? '');
            $modelUrl = (string) ($model['url'] ?? $source->base_url);

            if ($modelName === '') {
                continue;
            }

            $modifications = $model['modifications'] ?? $model['trims'] ?? $model['variants'] ?? null;

            if (is_array($modifications) && $modifications !== []) {
                foreach ($modifications as $mod) {
                    if (! is_array($mod)) {
                        continue;
                    }

                    $offer = $this->buildOffer(
                        source: $source,
                        brand: $brand,
                        modelName: $modelName,
                        trim: (string) ($mod['name'] ?? $mod['title'] ?? ''),
                        year: isset($mod['year']) ? (int) $mod['year'] : (isset($model['year']) ? (int) $model['year'] : null),
                        priceAmount: $this->extractPrice($mod),
                        url: (string) ($mod['url'] ?? $modelUrl),
                        observedAt: $observedAt,
                    );

                    if ($offer !== null) {
                        $offers[] = $offer;
                    }
                }

                continue;
            }

            $offer = $this->buildOffer(
                source: $source,
                brand: $brand,
                modelName: $modelName,
                trim: null,
                year: isset($model['year']) ? (int) $model['year'] : null,
                priceAmount: $this->extractPrice($model),
                url: $modelUrl,
                observedAt: $observedAt,
            );

            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<int, mixed>
     */
    private function extractModelList(array $json, Source $source): array
    {
        $path = $source->settings['json_path'] ?? null;

        if (is_string($path) && $path !== '') {
            $node = $json;
            foreach (explode('.', $path) as $key) {
                if (! is_array($node) || ! array_key_exists($key, $node)) {
                    return [];
                }
                $node = $node[$key];
            }

            return is_array($node) ? $node : [];
        }

        foreach (['models', 'data', 'items', 'result', 'catalog'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                if ($key === 'data' && isset($json[$key]['models']) && is_array($json[$key]['models'])) {
                    return $json[$key]['models'];
                }

                return $json[$key];
            }
        }

        return array_is_list($json) ? $json : [];
    }

    /** @param array<string, mixed> $row */
    private function extractPrice(array $row): int
    {
        $candidates = [
            $row['price'] ?? null,
            $row['price_amount'] ?? null,
            $row['min_price'] ?? null,
            $row['cost'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = $candidate['amount'] ?? $candidate['value'] ?? null;
            }

            if (is_numeric($candidate)) {
                return (int) round((float) $candidate);
            }

            if (is_string($candidate)) {
                $digits = preg_replace('/\D+/', '', $candidate);
                if ($digits !== '' && $digits !== null) {
                    return (int) $digits;
                }
            }
        }

        return 0;
    }

    private function buildOffer(
        Source $source,
        string $brand,
        string $modelName,
        ?string $trim,
        ?int $year,
        int $priceAmount,
        string $url,
        \DateTimeImmutable $observedAt,
    ): ?OfficialOfferData {
        if ($priceAmount <= 0) {
            return null;
        }

        $trimName = ($trim !== null && trim($trim) !== '') ? trim($trim) : null;

        $externalId = 'uzum-'.mb_strtolower(
            preg_replace('/[^a-zA-Z0-9]+/', '-', $modelName.'-'.($trimName ?? '').'-'.($year ?? ''))
        );

        $contentHash = $this->contentHashBuilder->build(
            self::SOURCE_CODE,
            $externalId,
            $url,
            $brand,
            $modelName,
            $year,
            $priceAmount,
            self::DEFAULT_CURRENCY,
            self::CONDITION,
        );

        return OfficialOfferData::fromArray([
            'source_id' => $source->id,
            'external_id' => $externalId,
            'url' => $url,
            'brand' => $brand,
            'model' => $modelName,
            'trim' => $trimName,
            'year' => $year,
            'price' => ['amount' => $priceAmount, 'currency' => self::DEFAULT_CURRENCY],
            'observed_at' => $observedAt->format(DATE_ATOM),
            'content_hash' => $contentHash,
        ]);
    }
}
