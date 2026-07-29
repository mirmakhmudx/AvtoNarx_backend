<?php

namespace App\Services\Parser\Adapters;

use App\Models\ParserTarget;
use App\Services\Parser\Exceptions\SourceBlockedException;
use App\Services\Parser\Extraction\ContentHashBuilder;
use App\Services\Parser\Extraction\MoneyExtractor;
use App\Services\Parser\Extraction\YearExtractor;
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

    // Bitta target (brend+model) uchun ko'rib chiqiladigan sahifalarning
    // yuqori chegarasi — xavfsizlik uchun (masalan mashhur model uchun
    // OLX'da yuzlab sahifa bo'lsa ham, bitta targetga cheksiz vaqt
    // sarflanmasligi kerak). TZ_PARSER.md'dagi tavsiya etilgan
    // max_pages=10 konfiguratsiyasi bilan mos.
    private const MAX_PAGES_PER_TARGET = 10;

    // Bitta target ichida sahifalar orasidagi qo'shimcha kutish — OLX'ga
    // haddan tashqari tez-tez so'rov yubormaslik uchun (chunk job'dagi
    // targetlar orasidagi 3s kutishdan tashqari, qo'shimcha).
    private const PAGE_REQUEST_DELAY_SECONDS = 2;

    public function __construct(
        private readonly MoneyExtractor $moneyExtractor,
        private readonly YearExtractor $yearExtractor,
        private readonly ContentHashBuilder $contentHashBuilder,
    ) {
    }

    /**
     * Bitta target (brend+model sahifasi)ning BARCHA sahifalarini ketma-ket
     * ko'rib chiqadi (page=1, page=2, ...), sahifa bo'sh kelguncha yoki
     * MAX_PAGES_PER_TARGET'ga yetguncha. Agar biror sahifada vaqtinchalik
     * xato (masalan HTTP 500) yoki bloklash chiqsa — target BUTUNLAY
     * muvaffaqiyatsiz deb hisoblanadi (hozirgacha yig'ilgan natijalar ham
     * tashlab yuboriladi), chunki chala natija bilan
     * ListingIngestionService::markMissingForModel chaqirilsa hali
     * ko'rilmagan keyingi sahifalardagi FAOL e'lonlar noto'g'ri "yo'qolgan"
     * deb belgilanib qolishi mumkin edi.
     */
    public function extractFromTarget(ParserTarget $target): array
    {
        $allResults = array();

        for ($page = 1; $page <= self::MAX_PAGES_PER_TARGET; $page++) {
            $pageResults = $this->fetchPage($target, $page);

            if ($pageResults === null) {
                // Sahifada e'lon topilmadi — oxirgi sahifaga yetdik, bu
                // xato emas, normal tugash.
                break;
            }

            $allResults = array_merge($allResults, $pageResults);

            if ($page < self::MAX_PAGES_PER_TARGET) {
                sleep(self::PAGE_REQUEST_DELAY_SECONDS);
            }
        }

        return $allResults;
    }

    /**
     * @return array<int, array{item: array|null, rejected_reason: string|null}>|null
     *         Sahifada kartochka topilmasa — null (oxirgi sahifa belgisi).
     */
    private function fetchPage(ParserTarget $target, int $page): ?array
    {
        $url = $this->buildPageUrl($target->target_url, $page);

        $response = Http::withHeaders(array(
            'User-Agent' => self::USER_AGENT,
        ))->timeout(15)->get($url);

        if ($response->status() === 403 || $response->status() === 429) {
            throw new SourceBlockedException('Manba bloklandi (HTTP ' . $response->status() . '). To\'xtatildi.');
        }

        if (! $response->successful()) {
            // Oddiy xato (404, 500, timeout va h.k.) — bloklash emas, lekin
            // shu target uchun to'liq muvaffaqiyatsizlik hisoblanadi (yuqoridagi
            // izohga qarang).
            throw new \RuntimeException("Sahifa {$page} yuklanmadi (HTTP " . $response->status() . ').');
        }

        $html = $response->body();
        unset($response);

        // Kartochkalar yo'q bo'lsa VA sahifa "haqiqatan bloklangan sahifa"ga
        // o'xshasa (juda qisqa HTML + captcha/robot so'zlari) — to'xtaymiz.
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

            // Haqiqiy bo'sh sahifa — target'ning oxirgi sahifasidan
            // o'tib ketdik, bu normal holat.
            unset($crawler);
            gc_collect_cycles();

            return null;
        }

        $results = array();

        $crawler->filter(self::CARD_SELECTOR)->each(function (Crawler $card) use (&$results, $target) {
            $results[] = $this->extractCard($card, $target);
        });

        // Katta HTML matni va DOM daraxtini ushlab turgan Crawler obyektini
        // to'liq bo'shatamiz — bitta job ichida 50-70 marta chaqirilganda
        // xotira asta-sekin to'planib ketmasligi uchun.
        unset($crawler);
        gc_collect_cycles();

        return $results;
    }

    /**
     * OLX pagination'i ?page=N query parametri orqali ishlaydi. Birinchi
     * sahifa uchun parametr shart emas (target_url'ning o'zi ham ishlaydi).
     */
    private function buildPageUrl(string $baseUrl, int $page): string
    {
        if ($page === 1) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . 'page=' . $page;
    }

    /**
     * Haqiqiy bloklash sahifasini aniqlaydi — oddiy so'z qidirish emas,
     * "kartochka yo'q + sahifa juda qisqa + blok so'zlari sarlavhada" kombinatsiyasi.
     */
    private function looksLikeBlockPageByLength(int $htmlLength, Crawler $crawler): bool
    {
        if ($htmlLength > 50000) {
            // To'liq katalog sahifasi keldi — bu blok sahifasi bo'lishi mumkin emas.
            return false;
        }

        $bodyText = mb_strtolower($crawler->text());

        return str_contains($bodyText, 'are you a robot')
            || str_contains($bodyText, 'access denied')
            || str_contains($bodyText, 'pardon the interruption');
    }

    /**
     * OLX joylashuv matni ikki xil formatda keladi:
     *  - "Ташкент, Сергелийский район - 21 июля 2026 г." (tuman bilan)
     *  - "Бухара - Сегодня в 08:15" (tumansiz, to'g'ridan-to'g'ri sana)
     * Avvalgi kod faqat vergul bo'yicha bo'lardi, shuning uchun ikkinchi
     * holatda sana/vaqt ham region sifatida saqlanib qolar edi. Endi
     * avval " - {sana}" qismini (u doim shu formatda keladi) kesib
     * tashlaymiz, keyin vergul bo'yicha faqat shahar nomini olamiz.
     */
    private function extractRegion(?string $locationText): ?string
    {
        if ($locationText === null || $locationText === '') {
            return null;
        }

        $withoutDate = trim(preg_replace('/\s*-\s*.*$/u', '', $locationText));
        $cityOnly = trim(explode(',', $withoutDate)[0]);

        return $cityOnly !== '' ? $cityOnly : null;
    }

    private function extractCard(Crawler $card, ParserTarget $target): array
    {
        $externalIdRaw = $card->attr('id');

        if (! $externalIdRaw) {
            return array('item' => null, 'rejected_reason' => 'missing_external_id');
        }

        $externalId = 'olx-' . $externalIdRaw;

        $priceNode = $card->filter(self::PRICE_SELECTOR);
        $priceText = $priceNode->count() > 0 ? trim($priceNode->text()) : '';

        $money = $this->moneyExtractor->extract($priceText);

        if ($money === null) {
            return array('item' => null, 'rejected_reason' => 'invalid_price');
        }

        $linkNode = $card->filter(self::LINK_SELECTOR)->first();
        $href = $linkNode->count() > 0 ? $linkNode->attr('href') : null;
        $canonicalUrl = $href ? self::BASE_URL . $href : null;

        if (! $canonicalUrl) {
            return array('item' => null, 'rejected_reason' => 'missing_url');
        }

        $locationNode = $card->filter(self::LOCATION_SELECTOR);
        $locationText = $locationNode->count() > 0 ? trim($locationNode->text()) : null;
        $region = $this->extractRegion($locationText);

        $titleNode = $card->filter(self::TITLE_SELECTOR);
        $titleText = $titleNode->count() > 0 ? trim($titleNode->text()) : '';

        $year = $this->yearExtractor->extract($titleText) ?? $this->yearExtractor->extract($card->text());

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
                'year' => $year,
                'price_amount' => $money['amount'],
                'currency' => $money['currency'],
                'condition' => 'unknown',
                'seller_type' => 'unknown',
                'region' => $region,
                'content_hash' => $contentHash,
            ),
            'rejected_reason' => null,
        );
    }
}
