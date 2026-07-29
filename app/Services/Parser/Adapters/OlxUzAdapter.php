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

    public function __construct(
        private readonly MoneyExtractor $moneyExtractor,
        private readonly YearExtractor $yearExtractor,
        private readonly ContentHashBuilder $contentHashBuilder,
    ) {
    }

    public function extractFromTarget(ParserTarget $target): array
    {
        $response = Http::withHeaders(array(
            'User-Agent' => self::USER_AGENT,
        ))->timeout(15)->get($target->target_url);

        if ($response->status() === 403 || $response->status() === 429) {
            throw new SourceBlockedException('Manba bloklandi (HTTP ' . $response->status() . '). To\'xtatildi.');
        }

        if (! $response->successful()) {
            // Oddiy xato (404, 500, timeout va h.k.) — bloklash emas.
            // Faqat shu targetni rad etamiz, boshqa targetlarga davom etish mumkin.
            throw new \RuntimeException('Sahifa yuklanmadi (HTTP ' . $response->status() . ').');
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

        if ($cardCount === 0 && $this->looksLikeBlockPageByLength($htmlLength, $crawler)) {
            unset($crawler);
            gc_collect_cycles();

            throw new SourceBlockedException('Bloklash sahifasi aniqlandi (CAPTCHA/robot tekshiruvi). To\'xtatildi.');
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
