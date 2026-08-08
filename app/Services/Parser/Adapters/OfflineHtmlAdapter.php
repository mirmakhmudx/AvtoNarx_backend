<?php

namespace App\Services\Parser\Adapters;

use App\Services\Parser\Extraction\ContentHashBuilder;
use App\Services\Parser\Extraction\ExternalIdBuilder;
use App\Services\Parser\Extraction\MoneyExtractor;
use App\Services\Parser\Extraction\UrlCanonicalizer;
use App\Services\Parser\Extraction\YearExtractor;
use Symfony\Component\DomCrawler\Crawler;

class OfflineHtmlAdapter
{
    private const SOURCE_CODE = 'olx_uz';

    private const BASE_URL = 'https://www.olx.uz';

    public function __construct(
        private readonly MoneyExtractor $moneyExtractor,
        private readonly YearExtractor $yearExtractor,
        private readonly UrlCanonicalizer $urlCanonicalizer,
        private readonly ExternalIdBuilder $externalIdBuilder,
        private readonly ContentHashBuilder $contentHashBuilder,
    ) {}

    /**
     * @return array<int, array{item:array|null, rejected_reason:string|null}>
     */
    public function extractFromFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException('Fixture fayl topilmadi: '.$filePath);
        }

        $html = file_get_contents($filePath);
        $crawler = new Crawler($html);

        $results = [];

        $crawler->filter('.listing-card')->each(function (Crawler $card) use (&$results) {
            $results[] = $this->extractCard($card);
        });

        return $results;
    }

    private function extractCard(Crawler $card): array
    {
        $titleNode = $card->filter('.listing-title');
        $priceNode = $card->filter('.listing-price');
        $linkNode = $card->filter('.listing-link');
        $locationNode = $card->filter('.listing-location');

        if ($titleNode->count() === 0 || $priceNode->count() === 0 || $linkNode->count() === 0) {
            return ['item' => null, 'rejected_reason' => 'missing_required_fields'];
        }

        $titleText = trim($titleNode->text());
        $priceText = trim($priceNode->text());
        $href = $linkNode->attr('href');
        $location = $locationNode->count() > 0 ? trim($locationNode->text()) : null;

        $canonicalUrl = $this->urlCanonicalizer->canonicalize(self::BASE_URL, $href);
        $externalId = $this->externalIdBuilder->build($canonicalUrl);

        $year = $this->yearExtractor->extract($titleText);

        $money = $this->moneyExtractor->extract($priceText);

        if ($money === null) {
            return ['item' => null, 'rejected_reason' => 'invalid_price'];
        }

        $vehicleNames = $this->splitBrandAndModel($titleText, $year);

        if ($vehicleNames === null) {
            return ['item' => null, 'rejected_reason' => 'ambiguous_vehicle_name'];
        }

        $contentHash = $this->contentHashBuilder->build(
            self::SOURCE_CODE,
            $externalId,
            $canonicalUrl,
            $vehicleNames['brand'],
            $vehicleNames['model'],
            $year,
            $money['amount'],
            $money['currency'],
            'unknown',
        );

        $item = [
            'source' => self::SOURCE_CODE,
            'external_id' => $externalId,
            'canonical_url' => $canonicalUrl,
            'brand_raw' => $vehicleNames['brand'],
            'model_raw' => $vehicleNames['model'],
            'year' => $year,
            'price_amount' => $money['amount'],
            'currency' => $money['currency'],
            'condition' => 'unknown',
            'seller_type' => 'unknown',
            'region' => $location,
            'content_hash' => $contentHash,
        ];

        return ['item' => $item, 'rejected_reason' => null];
    }

    /**
     * @return array{brand:string, model:string}|null
     */
    private function splitBrandAndModel(string $titleText, ?int $year): ?array
    {
        // Yil va vergul qismini olib tashlaymiz: "Chevrolet Cobalt LT, 2019" -> "Chevrolet Cobalt LT"
        $namePart = trim(preg_replace('/,?\s*\d{4}\s*$/', '', $titleText));
        $words = preg_split('/\s+/', $namePart);

        if (count($words) < 2) {
            return null;
        }

        $brand = $words[0];
        $model = $words[1];

        return ['brand' => $brand, 'model' => $model];
    }
}
