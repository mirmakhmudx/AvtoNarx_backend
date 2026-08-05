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

    private const MAX_ITEMS_PER_TARGET = 10;

    private const PAGE_REQUEST_DELAY_SECONDS = 2;
    private const HTTP_TIMEOUT_SECONDS = 20;
    private const MAX_ATTEMPTS_PER_PAGE = 2;
    private const RETRY_DELAY_SECONDS = 3;

    public function __construct(
        private readonly MoneyExtractor $moneyExtractor,
        private readonly YearExtractor $yearExtractor,
        private readonly ContentHashBuilder $contentHashBuilder,
        private readonly TitleModelMatcher $titleModelMatcher,
    ) {
    }


    public function extractFromTarget(ParserTarget $target): array
    {
        $allResults = array();

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

        return array('results' => $allResults, 'complete' => true, 'error' => null);
    }


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

    /**
     * SELEKTORGA TAYANMAYDIGAN, ISHONCHLI yil qidiruvi: OLX'da har bir
     * kartochkada "ISHLAB CHIQARISH YILI - PROBEG" formatidagi qator
     * bo'ladi. Ikki ko'rinishda uchraydi:
     *  - "2008 - 385 000 км" (yurgan bo'lsa, "км" bilan)
     *  - "2026 - 0" (yangi/0 km mashina — "км" so'zisiz!)
     * Shuning uchun ikkalasini ham qamrab olamiz: yil'dan keyin tire, so'ng
     * "N NNN км" YOKI yolg'iz "0". Bu naqish joylashuv sanasida ("21 iyul
     * 2026 й.") HECH QACHON uchramaydi, chunki u yildan keyin darhol tire
     * bilan davom etmaydi (" г." bilan tugaydi).
     */
    private function extractYearFromCardText(string $cardText): ?int
    {
        if (preg_match('/\b(19[5-9]\d|20\d{2})\b\s*-\s*(?:[\d\s\x{00A0}]*км\b|0\b)/u', $cardText, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractCard(Crawler $card, ParserTarget $target): array
    {
        $externalIdRaw = $card->attr('id');

        if (! $externalIdRaw) {
            return array('item' => null, 'rejected_reason' => 'missing_external_id', 'title_raw' => null);
        }

        $externalId = 'olx-' . $externalIdRaw;

        $priceNode = $card->filter(self::PRICE_SELECTOR);
        $priceText = $priceNode->count() > 0 ? trim($priceNode->text()) : '';

        $money = $this->moneyExtractor->extract($priceText);

        if ($money === null) {
            return array('item' => null, 'rejected_reason' => 'invalid_price', 'title_raw' => null);
        }

        $linkNode = $card->filter(self::LINK_SELECTOR)->first();
        $href = $linkNode->count() > 0 ? $linkNode->attr('href') : null;
        $canonicalUrl = $href ? self::BASE_URL . $href : null;

        if (! $canonicalUrl) {
            return array('item' => null, 'rejected_reason' => 'missing_url', 'title_raw' => null);
        }

        $titleNode = $card->filter(self::TITLE_SELECTOR);
        $titleText = $titleNode->count() > 0 ? trim($titleNode->text()) : '';

        // Faqat ENG ANIQ chiqindi belgisi — OLX'ning "hech narsa topilmadi,
        // o'xshashlarini ko'ring" fallback natijasi. Bu — butunlay bog'liq
        // bo'lmagan narsalar (soat, uy) shu orqali kirib kelgan edi.
        if (str_contains($canonicalUrl, 'reason=extended_search')) {
            return array('item' => null, 'rejected_reason' => 'olx_fallback_result', 'title_raw' => $titleText);
        }

        // MUHIM QAROR (qayta ko'rib chiqildi): sarlavha bo'yicha marka/model
        // tekshiruvi AVVAL olib tashlangan edi ("target o'ziga ishonaylik"
        // degan g'oya bilan), lekin bu bir xil marka ichida BOSHQA modelni
        // (masalan "JAC iEVS4" target'ida "JAC Pickup" e'loni) aralashtirib
        // yuborishga olib keldi — bu qabul qilinmaydigan xato. Shuning
        // uchun tekshiruv QAYTA yoqildi. TitleModelMatcher endi kirillcha
        // transliteratsiya va kichik yozuv xatolariga (1-2 harf) toqatli,
        // shuning uchun haqiqiy e'lonlarni ortiqcha rad etmasligi kerak.
        if (! $this->titleModelMatcher->matches($titleText, $target->carModel->name)) {
            return array('item' => null, 'rejected_reason' => 'title_model_mismatch', 'title_raw' => $titleText);
        }

        $locationNode = $card->filter(self::LOCATION_SELECTOR);
        $locationText = $locationNode->count() > 0 ? trim($locationNode->text()) : null;
        $region = $this->extractRegion($locationText);

        // Yil: avval sarlavhadan, topilmasa — kartochkaning TO'LIQ matnidan
        // "YIL - PROBEG км" naqshi orqali (yuqoridagi izohga qarang, bu
        // sana bilan chalkashib ketmaydi).
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
