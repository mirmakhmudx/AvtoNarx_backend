<?php

namespace App\Services\Parser\Adapters;

use App\DTO\ListingData;
use App\Models\Source;
use App\Services\Parser\Extraction\ContentHashBuilder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Uzum Avto (webview.uzumavto.uz) — bu ISHLATILGAN mashinalar BOZORI (OLX kabi),
 * ishlab chiqaruvchi/salon EMAS. Shu sabab adapter market_listings uchun
 * ListingData ishlab chiqaradi (condition = used), OfficialOffer emas.
 *
 * Endpoint va struktura manba sozlamalaridan olinadi:
 *   settings.catalog_endpoint  — feed/list API to'liq URL (majburiy)
 *   settings.json_path         — e'lonlar ro'yxati JSON ichida qayerda ("data.items")
 */
class UzumAvtoAdapter
{
    private const SOURCE_CODE = 'uzum_avto';

    private const CONDITION = 'used';

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
                'User-Agent' => 'AvtoNarx/1.0 (+market-price-collector)',
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

    /** @return array<int, ListingData> */
    public function fetchListings(Source $source, ?string $overrideUrl = null): array
    {
        return $this->mapListings($this->fetchRaw($source, $overrideUrl), $source);
    }

    /**
     * ⚠️ MOSLASH KERAK BO'LGAN YAGONA JOY — Uzum JSON'ini ListingData'ga
     * o'tkazadi. Haqiqiy JSON boshqacha bo'lsa, faqat shu metod ichini
     * o'zgartiring; qolgan hamma narsa o'zgarishsiz ishlaydi.
     *
     * @param  array<string, mixed>  $json
     * @return array<int, ListingData>
     */
    public function mapListings(array $json, Source $source): array
    {
        $items = $this->extractItemList($json, $source);
        $listings = [];
        $observedAt = now()->toIso8601String();

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $externalId = (string) ($item['id'] ?? $item['external_id'] ?? $item['uuid'] ?? '');
            $brand = (string) ($item['brand'] ?? $item['brand_name'] ?? '');
            $model = (string) ($item['model'] ?? $item['model_name'] ?? $item['title'] ?? '');
            $price = $this->extractPrice($item);

            if ($externalId === '' || $model === '' || $price <= 0) {
                continue;
            }

            $url = (string) ($item['url'] ?? ($source->base_url.'/uz/details/'.$externalId));
            $year = isset($item['year']) ? (int) $item['year'] : null;
            $region = $item['region'] ?? ($item['location']['region'] ?? null);

            $contentHash = $this->contentHashBuilder->build(
                self::SOURCE_CODE,
                $externalId,
                $url,
                $brand,
                $model,
                $year,
                $price,
                self::DEFAULT_CURRENCY,
                self::CONDITION,
            );

            $listings[] = ListingData::fromArray([
                'source_id' => $source->id,
                'external_id' => $externalId,
                'canonical_url' => $url,
                'brand_raw' => $brand !== '' ? $brand : null,
                'model_raw' => $model,
                'year' => $year,
                'price_amount' => $price,
                'currency' => self::DEFAULT_CURRENCY,
                'condition' => self::CONDITION,
                'seller_type' => (string) ($item['seller_type'] ?? 'unknown'),
                'region' => $region,
                'source_published_at' => $observedAt,
                'content_hash' => $contentHash,
            ]);
        }

        return $listings;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<int, mixed>
     */
    private function extractItemList(array $json, Source $source): array
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

        foreach (['items', 'data', 'results', 'feed', 'listings'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                if ($key === 'data' && isset($json[$key]['items']) && is_array($json[$key]['items'])) {
                    return $json[$key]['items'];
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
}
