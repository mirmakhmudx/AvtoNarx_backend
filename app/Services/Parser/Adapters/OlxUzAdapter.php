<?php

namespace App\Services\Parser\Adapters;

use App\Models\ParserTarget;
use App\Services\Parser\Exceptions\SourceBlockedException;
use App\Services\Parser\Extraction\ContentHashBuilder;
use App\Services\Parser\Extraction\MoneyExtractor;
use App\Services\Parser\Extraction\TitleModelMatcher;
use App\Services\Parser\Extraction\YearExtractor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class OlxUzAdapter
{
    private const SOURCE_CODE = 'olx_uz';
    private const BASE_URL = 'https://www.olx.uz';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const CARD_SELECTOR = '[data-cy=l-card]';
    private const TITLE_SELECTOR = '[data-cy="ad-card-title"] h4';
    private const PRICE_SELECTOR = '[data-testid="ad-price"]';
    private const LOCATION_SELECTOR = '[data-testid="location-date"]';
    private const LINK_SELECTOR = 'a[href^="/d/obyavlenie/"]';

    private const MAX_PAGES_PER_TARGET = 10;
    private const MAX_CONSECUTIVE_EMPTY_PAGES = 2;


    private const MAX_ITEMS_PER_TARGET = 30;

    private const PAGE_REQUEST_DELAY_SECONDS = 2;
    private const HTTP_TIMEOUT_SECONDS = 20;
    private const MAX_ATTEMPTS_PER_PAGE = 2;
    private const RETRY_DELAY_SECONDS = 3;

    private const DETAIL_PAGE_REQUEST_DELAY_SECONDS = 1;

    public function __construct(
        private readonly MoneyExtractor $moneyExtractor,
        private readonly YearExtractor $yearExtractor,
        private readonly ContentHashBuilder $contentHashBuilder,
        private readonly TitleModelMatcher $titleModelMatcher,
    ) {
    }

    /**
     * @return array{results: array<int, array{item: array|null, rejected_reason: string|null, title_raw: string|null}>, complete: bool, error: string|null}
     */
    public function extractFromTarget(ParserTarget $target): array
    {
        $allResults = array();
        $consecutiveEmptyPages = 0;

        for ($page = 1; $page <= self::MAX_PAGES_PER_TARGET; $page++) {
            try {
                $pageResults = $this->fetchPage($target, $page);
            } catch (SourceBlockedException $e) {
                throw $e;
            } catch (\RuntimeException $e) {
                return array(
                    'results' => $allResults,
                    'complete' => false,
                    'error' => $e->getMessage(),
                );
            }

            if ($pageResults === null) {
                break;
            }

            $allResults = array_merge($allResults, $pageResults);

            $matchedOnThisPage = 0;
            foreach ($pageResults as $r) {
                if ($r['item'] !== null) {
                    $matchedOnThisPage++;
                }
            }

            if ($matchedOnThisPage === 0) {
                $consecutiveEmptyPages++;
            } else {
                $consecutiveEmptyPages = 0;
            }

            if ($consecutiveEmptyPages >= self::MAX_CONSECUTIVE_EMPTY_PAGES) {
                break;
            }

            $collectedCount = 0;
            foreach ($allResults as $r) {
                if ($r['item'] !== null) {
                    $collectedCount++;
                }
            }

            if ($collectedCount >= self::MAX_ITEMS_PER_TARGET) {
                break;
            }

            if ($page < self::MAX_PAGES_PER_TARGET) {
                sleep(self::PAGE_REQUEST_DELAY_SECONDS);
            }
        }

        // YIL TO'LDIRISH BOSQICHI: kartochkada yil topilmagan, lekin
        // QABUL QILINGAN elementlar uchun — e'lonning TO'LIQ sahifasiga
        // alohida kirib, aniq "Год выпуска: XXXX" ni olamiz. Faqat
        // ZARUR bo'lganda (yil hali yo'q bo'lsa) qilinadi, shuning uchun
        // qo'shimcha so'rovlar soni cheklangan (odatda ~10 tagacha).
        foreach ($allResults as $i => $r) {
            if ($r['item'] !== null && $r['item']['year'] === null) {
                sleep(self::DETAIL_PAGE_REQUEST_DELAY_SECONDS);

                $detailYear = $this->fetchYearFromDetailPage($r['item']['canonical_url']);

                if ($detailYear !== null) {
                    $allResults[$i]['item']['year'] = $detailYear;
                }
            }
        }

        return array('results' => $allResults, 'complete' => true, 'error' => null);
    }

    /**
     * @return array<int, array{item: array|null, rejected_reason: string|null, title_raw: string|null}>|null
     */
    private function fetchPage(ParserTarget $target, int $page): ?array
    {
        $url = $this->buildPageUrl($target->target_url, $page);

        $html = $this->fetchHtmlWithRetry($url, $page);

        $cleanHtml = preg_replace('#<style[^>]*>.*?</style>#si', '', $html);

        $htmlLength = strlen($html);
        unset($html);

        $crawler = new Crawler($cleanHtml);
        unset($cleanHtml);

        $cardCount = $crawler->filter(self::CARD_SELECTOR)->count();

        if ($cardCount === 0) {
            if ($this->looksLikeBlockPageByLength($htmlLength, $crawler)) {
                unset($crawler);
                gc_collect_cycles();

                throw new SourceBlockedException('Bloklash sahifasi aniqlandi (CAPTCHA/robot tekshiruvi). To\'xtatildi.');
            }

            unset($crawler);
            gc_collect_cycles();

            return null;
        }

        $results = array();

        $crawler->filter(self::CARD_SELECTOR)->each(function (Crawler $card) use (&$results, $target) {
            $results[] = $this->extractCard($card, $target);
        });

        unset($crawler);
        gc_collect_cycles();

        return $results;
    }

    private function fetchHtmlWithRetry(string $url, int $page): string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS_PER_PAGE; $attempt++) {
            $isLastAttempt = $attempt === self::MAX_ATTEMPTS_PER_PAGE;

            try {
                $response = Http::withHeaders(array(
                    'User-Agent' => self::USER_AGENT,
                ))->timeout(self::HTTP_TIMEOUT_SECONDS)->get($url);
            } catch (ConnectionException $e) {
                if ($isLastAttempt) {
                    throw new \RuntimeException(
                        "Sahifa {$page} yuklanmadi (tarmoq xatosi, {$attempt} urinishdan keyin): " . $e->getMessage()
                    );
                }

                sleep(self::RETRY_DELAY_SECONDS);

                continue;
            }

            if ($response->status() === 403 || $response->status() === 429) {
                throw new SourceBlockedException('Manba bloklandi (HTTP ' . $response->status() . '). To\'xtatildi.');
            }

            if ($response->serverError()) {
                if ($isLastAttempt) {
                    throw new \RuntimeException("Sahifa {$page} yuklanmadi (HTTP " . $response->status() . ", {$attempt} urinishdan keyin).");
                }

                sleep(self::RETRY_DELAY_SECONDS);

                continue;
            }

            if (! $response->successful()) {
                throw new \RuntimeException("Sahifa {$page} yuklanmadi (HTTP " . $response->status() . ').');
            }

            return $response->body();
        }

        throw new \RuntimeException("Sahifa {$page} yuklanmadi (noma'lum xato).");
    }

    private function buildPageUrl(string $baseUrl, int $page): string
    {
        if ($page === 1) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . 'page=' . $page;
    }

    private function looksLikeBlockPageByLength(int $htmlLength, Crawler $crawler): bool
    {
        if ($htmlLength > 50000) {
            return false;
        }

        $bodyText = mb_strtolower($crawler->text());

        return str_contains($bodyText, 'are you a robot')
            || str_contains($bodyText, 'access denied')
            || str_contains($bodyText, 'pardon the interruption');
    }

    private function extractRegion(?string $locationText): ?string
    {
        if ($locationText === null || $locationText === '') {
            return null;
        }

        $withoutDate = trim(preg_replace('/\s*-\s*.*$/u', '', $locationText));
        $cityOnly = trim(explode(',', $withoutDate)[0]);

        return $cityOnly !== '' ? $cityOnly : null;
    }

    private function extractYearFromCardText(string $cardText): ?int
    {
        if (preg_match('/\b(19[5-9]\d|20\d{2})\b\s*-\s*(?:[\d\s\x{00A0}]*км\b|0\b)/u', $cardText, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Kartochkada yil topilmagan hollar uchun ZAXIRA usul: e'lonning
     * TO'LIQ sahifasiga alohida so'rov yuborib, u yerdagi rasmiy
     * "Год выпуска: XXXX" parametridan aniq yilni o'qiydi. Bu — eng
     * ISHONCHLI manba, chunki bu sotuvchi to'ldirgan rasmiy forma maydoni,
     * sarlavha yoki qidiruv natijasidagi taxminiy matn emas.
     *
     * Xato/timeout bo'lsa jimgina null qaytaradi — bitta e'lonning yili
     * topilmasa ham, butun jarayonni to'xtatishga arzimaydi.
     */
    private function fetchYearFromDetailPage(string $url): ?int
    {
        try {
            $response = Http::withHeaders(array(
                'User-Agent' => self::USER_AGENT,
            ))->timeout(self::HTTP_TIMEOUT_SECONDS)->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        // OLX'da bu maydon "Год выпуска: 2020" formatida, alohida
        // "belgi" (chip) sifatida chiqadi (rasmga qarang — Toyota Avalon
        // misolida ham xuddi shunday edi).
        if (preg_match('/Год\s+выпуска[:\s]*(\d{4})/u', $response->body(), $matches)) {
            $year = (int) $matches[1];

            if ($year >= 1950 && $year <= (int) date('Y') + 1) {
                return $year;
            }
        }

        return null;
    }

    private function extractCard(Crawler $card, ParserTarget $target): array
    {
        $externalIdRaw = $card->attr('id');

        if (! $externalIdRaw) {
            return array('item' => null, 'rejected_reason' => 'missing_external_id', 'title_raw' => null, 'canonical_url' => null);
        }

        $externalId = 'olx-' . $externalIdRaw;

        $priceNode = $card->filter(self::PRICE_SELECTOR);
        $priceText = $priceNode->count() > 0 ? trim($priceNode->text()) : '';

        $money = $this->moneyExtractor->extract($priceText);

        if ($money === null) {
            return array('item' => null, 'rejected_reason' => 'invalid_price', 'title_raw' => null, 'canonical_url' => null);
        }

        $linkNode = $card->filter(self::LINK_SELECTOR)->first();
        $href = $linkNode->count() > 0 ? $linkNode->attr('href') : null;
        $canonicalUrl = $href ? self::BASE_URL . $href : null;

        if (! $canonicalUrl) {
            return array('item' => null, 'rejected_reason' => 'missing_url', 'title_raw' => null, 'canonical_url' => null);
        }

        $titleNode = $card->filter(self::TITLE_SELECTOR);
        $titleText = $titleNode->count() > 0 ? trim($titleNode->text()) : '';

        if (str_contains($canonicalUrl, 'reason=extended_search')) {
            return array('item' => null, 'rejected_reason' => 'olx_fallback_result', 'title_raw' => $titleText, 'canonical_url' => $canonicalUrl);
        }

        if (! $this->titleModelMatcher->matches($titleText, $target->carModel->name)) {
            return array('item' => null, 'rejected_reason' => 'title_model_mismatch', 'title_raw' => $titleText, 'canonical_url' => $canonicalUrl);
        }

        $locationNode = $card->filter(self::LOCATION_SELECTOR);
        $locationText = $locationNode->count() > 0 ? trim($locationNode->text()) : null;
        $region = $this->extractRegion($locationText);

        // Yil: avval sarlavhadan, keyin kartochka matnidagi "YIL - PROBEG"
        // naqshidan. Bu yerda ham topilmasa — null qoladi, va
        // extractFromTarget() darajasida (yuqorida) detail sahifadan
        // ZAXIRA sifatida olinishga urinilib ko'riladi.
        $year = $this->yearExtractor->extract($titleText) ?? $this->extractYearFromCardText($card->text());

        $brandRaw = $target->brand->name;
        $modelRaw = $target->carModel->name;

        $contentHash = $this->contentHashBuilder->build(
            self::SOURCE_CODE,
            $externalId,
            $canonicalUrl,
            $brandRaw,
            $modelRaw,
            $year,
            $money['amount'],
            $money['currency'],
            'unknown',
        );

        return array(
            'item' => array(
                'source_id' => $target->source_id,
                'external_id' => $externalId,
                'canonical_url' => $canonicalUrl,
                'brand_raw' => $brandRaw,
                'model_raw' => $modelRaw,
                'known_brand_id' => $target->brand_id,
                'known_model_id' => $target->model_id,
                'year' => $year,
                'price_amount' => $money['amount'],
                'currency' => $money['currency'],
                'condition' => 'unknown',
                'seller_type' => 'unknown',
                'region' => $region,
                'content_hash' => $contentHash,
            ),
            'rejected_reason' => null,
            'title_raw' => $titleText,
        );
    }
}
