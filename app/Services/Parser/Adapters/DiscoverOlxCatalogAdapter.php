<?php

namespace App\Services\Parser\Adapters;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class DiscoverOlxCatalogAdapter
{
    private const BASE_URL = 'https://www.olx.uz';

    private const CATEGORY_PATH = '/transport/legkovye-avtomobili/';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const EXCLUDED_BRAND_NAME_KEYWORDS = ['область', 'каракалпакстан', 'другие'];

    public function discoverBrands(): array
    {
        $html = $this->fetch(self::BASE_URL.self::CATEGORY_PATH);
        $crawler = new Crawler($html);

        $brands = [];
        $seenSlugs = [];

        $crawler->filter('a[href*="'.self::CATEGORY_PATH.'"]')->each(function (Crawler $node) use (&$brands, &$seenSlugs) {
            $href = $node->attr('href');
            $text = trim($node->text());

            if ($href === null || str_starts_with($href, '/oz/')) {
                return;
            }

            // Faqat BIR segmentli href'lar (marka darajasi): /transport/.../chevrolet/
            if (! preg_match('#^'.preg_quote(self::CATEGORY_PATH, '#').'([a-z0-9\-]+)/$#i', $href, $matches)) {
                return;
            }

            $slug = $matches[1];

            if (str_starts_with($slug, 'q-')) {
                return;
            }

            if (isset($seenSlugs[$slug])) {
                return;
            }

            // "Chevrolet40 217" -> "Chevrolet"
            if (! preg_match('/^([^\d]+)/u', $text, $nameMatch)) {
                return;
            }

            $name = trim($nameMatch[1], " \t\n\r\0\x0B(");

            if ($name === '') {
                return;
            }

            // Viloyat nomlari va "Другие" (Boshqalar) — bular filtrlash
            // bo'limidan, marka emas.
            $lowerName = mb_strtolower($name);
            foreach (self::EXCLUDED_BRAND_NAME_KEYWORDS as $keyword) {
                if (str_contains($lowerName, $keyword)) {
                    return;
                }
            }

            $seenSlugs[$slug] = true;

            $brands[] = [
                'name' => $name,
                'slug' => $slug,
                'url' => self::BASE_URL.$href,
            ];
        });

        return $brands;
    }

    public function discoverModels(string $brandSlug): array
    {
        $brandPath = self::CATEGORY_PATH.$brandSlug.'/';
        $html = $this->fetch(self::BASE_URL.$brandPath);
        $crawler = new Crawler($html);

        $models = [];
        $seenSlugs = [];

        $crawler->filter('a[href*="'.$brandPath.'"]')->each(function (Crawler $node) use (&$models, &$seenSlugs, $brandPath) {
            $href = $node->attr('href');
            $text = trim($node->text());

            if ($href === null || $text === '') {
                return;
            }

            // Faqat ikki segmentli href: /transport/.../chevrolet/cobalt/
            $pattern = '#^'.preg_quote($brandPath, '#').'([a-z0-9\-]+)/$#i';

            if (! preg_match($pattern, $href, $matches)) {
                return;
            }

            $slug = $matches[1];

            // Viloyat, qidiruv taklifi, "boshqa" kabi noto'g'ri yozuvlarni chiqarib tashlaymiz
            if (str_contains($text, '(')
                || str_starts_with($slug, 'q-')
                || $slug === 'drugaya'
                || preg_match('/^\d+$/', $text)
            ) {
                return;
            }

            if (isset($seenSlugs[$slug])) {
                return;
            }

            $seenSlugs[$slug] = true;

            $models[] = [
                'name' => $text,
                'slug' => $slug,
                'url' => self::BASE_URL.$href,
            ];
        });

        return $models;
    }

    private function fetch(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
        ])->timeout(15)->get($url);

        if ($response->status() === 403 || $response->status() === 429) {
            throw new \RuntimeException('Manba bloklandi (HTTP '.$response->status().'). To\'xtatildi.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Sahifa yuklanmadi (HTTP '.$response->status().').');
        }

        return preg_replace('#<style[^>]*>.*?</style>#si', '', $response->body());
    }
}
