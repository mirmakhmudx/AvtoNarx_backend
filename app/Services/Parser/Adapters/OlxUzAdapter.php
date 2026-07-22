<?php

namespace App\Services\Parser\Adapters;

use App\Services\Parser\Contracts\SourceAdapterInterface;
use App\Services\Parser\Extraction\ContentHashBuilder;
use App\Services\Parser\Extraction\ExternalIdBuilder;
use App\Services\Parser\Extraction\MoneyExtractor;
use App\Services\Parser\Extraction\UrlCanonicalizer;
use App\Services\Parser\Extraction\YearExtractor;
use Spekulatius\PHPScraper\PHPScraper;
use Symfony\Component\DomCrawler\Crawler;

class OlxUzAdapter implements SourceAdapterInterface
{
    private const SOURCE_CODE = 'olx_uz';
    private const BASE_URL = 'https://www.olx.uz';

    private const REQUEST_DELAY_SECONDS = 3;

    public function __construct(
        private readonly MoneyExtractor     $moneyExtractor,
        private readonly YearExtractor      $yearExtractor,
        private readonly UrlCanonicalizer   $urlCanonicalizer,
        private readonly ExternalIdBuilder  $externalIdBuilder,
        private readonly ContentHashBuilder $contentHashBuilder,
    )
    {
    }

    public function sourceCode(): string
    {
        return self::SOURCE_CODE;
    }

    public function discoverTargets(): array
    {
        return array(
            self::BASE_URL . '/transport/legkovye-avtomobili/chevrolet/cobalt/tashkent/',
        );
    }

    public function extractFromUrl(string $url): array
    {
        $web = new PHPScraper();
        $web->setConfig([
            'agent' => 'AvtoNarxParser/1.0 (+contact: you@example.uz)',
            'timeout' => 15,
        ]);

        try {
            $web->go($url);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Sahifaga ulanib bo\'lmadi: ' . $e->getMessage());
        }

        // PHPScraper alohida html()/statusCode() metodlarini bermaydi —
        // buning o'rniga BrowserKit'ning haqiqiy Response obyektiga chiqamiz.
        $response = $web->client()->getResponse();
        $statusCode = $response->getStatusCode();

        if ($statusCode === 403 || $statusCode === 429) {
            throw new \RuntimeException('Manba bloklandi (HTTP ' . $statusCode . '). To\'xtatildi, qayta urinilmadi.');
        }

        if ($statusCode < 200 || $statusCode >= 400) {
            throw new \RuntimeException('Kutilmagan HTTP holati: ' . $statusCode);
        }

        $html = $response->getContent();

        if ($this->looksLikeCaptcha($html)) {
            throw new \RuntimeException('CAPTCHA aniqlandi. Manba to\'xtatildi, aylanib o\'tilmadi.');
        }

        // PHPScraper'ning o'z filter() metodi — currentPage ustida filterXPath
        // bajaradi va haqiqiy Symfony DomCrawler\Crawler qaytaradi.
        $listingLinks = $web->filter('//a[contains(@href, "/d/obyavlenie/")]');

        if ($listingLinks->count() === 0) {
            // Bo'sh natija emas — aniq xato, chunki bu DOM o'zgargani yoki
            // sahifa JS orqali render qilinayotganidan darak berishi mumkin.
            throw new \RuntimeException('E\'lon havolalari topilmadi — sahifa strukturasi o\'zgargan yoki JS render kerak bo\'lishi mumkin.');
        }

        $seenHrefs = array();
        $results = array();

        $listingLinks->each(function (Crawler $link) use (&$results, &$seenHrefs) {
            $href = $link->attr('href');

            if ($href === null || isset($seenHrefs[$href])) {
                return;
            }

            $seenHrefs[$href] = true;

            $card = $this->findCardAncestor($link);

            if ($card === null) {
                $results[] = array('item' => null, 'rejected_reason' => 'card_container_not_found');

                return;
            }

            $results[] = $this->extractCard($card, $href, $link);
        });

        sleep(self::REQUEST_DELAY_SECONDS);

        return $results;
    }

    private function findCardAncestor(Crawler $link): ?Crawler
    {
        $node = $link->getNode(0);

        if ($node === null) {
            return null;
        }

        $current = $node->parentNode;

        for ($i = 0; $i < 6 && $current !== null; $i++) {
            if ($current instanceof \DOMElement) {
                $candidate = new Crawler($current);
                $text = $this->cleanNodeText($candidate);

                if ($this->moneyExtractor->extract($text) !== null || str_contains(mb_strtolower($text), 'договорная')) {
                    return $candidate;
                }
            }

            $current = $current->parentNode;
        }

        return null;
    }

    /**
     * Haqiqiy blok sahifasini frontend konfiguratsiyasidagi "recaptcha"
     * so'zidan farqlaymiz — oddiy "captcha" mavjudligi yetarli emas,
     * chunki ko'p sayt forma himoyasi uchun reCAPTCHA sozlamasini har doim
     * yuklaydi, bu sahifa bloklangani degani emas.
     */
    /**
     * OLX zamonaviy frontendida har bir kartochka ichiga <style> (Emotion
     * CSS-in-JS) in-line qo'shiladi. Oddiy ->text() shu CSS matnini ham
     * qo'shib yuboradi. Shuning uchun <style>/<script> teglarini chetlab
     * o'tib, faqat ko'rinadigan matnni yig'amiz.
     */
    private function extractCleanText(\DOMNode $element): string
    {
        $text = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text .= $child->wholeText;
            } elseif ($child instanceof \DOMElement) {
                if (in_array(strtolower($child->tagName), ['style', 'script'], true)) {
                    continue;
                }

                $text .= ' ' . $this->extractCleanText($child);
            }
        }

        return $text;
    }

    private function cleanNodeText(Crawler $crawler): string
    {
        $node = $crawler->getNode(0);

        if ($node === null) {
            return '';
        }

        $text = $this->extractCleanText($node);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function looksLikeCaptcha(string $html): bool
    {
        $lowerHtml = mb_strtolower($html);

        $blockPhrases = [
            'checking your browser',
            'verify you are a human',
            'verify you are human',
            'are you a robot',
            'access to this page has been denied',
            'access denied',
            'cf-browser-verification',
            'attention required! | cloudflare',
            'please complete the security check',
        ];

        foreach ($blockPhrases as $phrase) {
            if (str_contains($lowerHtml, $phrase)) {
                return true;
            }
        }

        if (mb_strlen($html) < 5000) {
            return true;
        }

        return false;
    }

    private function extractCard(Crawler $card, string $href, Crawler $link): array
    {
        $fullText = $this->cleanNodeText($card);

        $canonicalUrl = $this->urlCanonicalizer->canonicalize(self::BASE_URL, $href);
        $externalId = $this->externalIdBuilder->build($canonicalUrl);

        $titleText = $this->cleanNodeText($link);

        if ($titleText === '') {
            return array('item' => null, 'rejected_reason' => 'missing_required_fields');
        }

        $money = $this->moneyExtractor->extract($fullText);

        if ($money === null) {
            return array('item' => null, 'rejected_reason' => 'invalid_price');
        }

        $year = $this->yearExtractor->extract($fullText);

        $region = null;
        if (preg_match('/Ташкент,\s*([^\-–\n]+)/u', $fullText, $m)) {
            $region = trim($m[1]);
        }

        $namePart = trim(preg_replace('/,?\s*\d{4}\s*$/', '', $titleText));
        $words = preg_split('/\s+/', $namePart);

        if (count($words) < 2) {
            return array('item' => null, 'rejected_reason' => 'ambiguous_vehicle_name');
        }

        $brand = $words[0];
        $model = $words[1];

        $contentHash = $this->contentHashBuilder->build(
            self::SOURCE_CODE,
            $externalId,
            $canonicalUrl,
            $brand,
            $model,
            $year,
            $money['amount'],
            $money['currency'],
            'unknown',
        );

        return array(
            'item' => array(
                'source' => self::SOURCE_CODE,
                'external_id' => $externalId,
                'canonical_url' => $canonicalUrl,
                'brand_raw' => $brand,
                'model_raw' => $model,
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
