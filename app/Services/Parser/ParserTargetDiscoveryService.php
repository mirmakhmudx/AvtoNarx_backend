<?php

namespace App\Services\Parser;

use App\Enums\EntityType;
use App\Models\CarModel;
use App\Models\ParserTarget;
use App\Models\Source;
use App\Models\UnmatchedBrandModelCandidate;
use App\Services\Catalog\CatalogAliasService;

class ParserTargetDiscoveryService
{
    /**
     * Model kodlarida (A5, X5, E200 kabi) uchraydigan, ko'rinishi bir xil
     * kirill/lotin harflar. Faqat "pending" navbatidagi vizual duplikatlarni
     * bitta yozuvga birlashtirish uchun ishlatiladi — katalogga yangi model
     * yaratish uchun EMAS (bu TZ 10-bo'lim bo'yicha taqiqlangan).
     */
    private const CYRILLIC_TO_LATIN_MAP = [
        'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M',
        'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X',
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'х' => 'x',
    ];

    private const REJECT_KEYWORDS = ['область', 'каракалпакстан', 'другие', 'показать', 'все категории'];

    public function __construct(
        private readonly CatalogAliasService $aliasService,
    ) {}

    /**
     * TZ 10-bo'lim, so'zma-so'z: "Parser payload'idan yangi markalar va
     * modellarni avtomatik yaratish taqiqlangan." Shuning uchun bu metod
     * HECH QACHON Brand yoki CarModel yaratmaydi — faqat:
     *   1) allaqachon tasdiqlangan (verified) alias orqali mos kelsa —
     *      parser_target faollashtiriladi;
     *   2) mos kelmasa — "chiqindi" (viloyat, sahifalash, bo'sh) nomlar
     *      chiqarib tashlanadi, qolgani esa unmatched_brand_model_candidates
     *      navbatiga tushadi va Muharrir (Content Editor) tomonidan qo'lda
     *      ko'rib chiqilishini kutadi.
     *
     * @return array{matched: int, unmatched: int, skipped_junk: int}
     */
    public function processDiscoveredCombinations(Source $source, array $discovered): array
    {
        $matchedCount = 0;
        $unmatchedCount = 0;
        $skippedJunkCount = 0;

        foreach ($discovered as $entry) {
            $brandId = $this->aliasService->resolve(EntityType::Brand, $entry['brand_name'], $source->id);
            $modelId = $brandId ? $this->aliasService->resolve(EntityType::Model, $entry['model_name'], $source->id) : null;

            if ($brandId && $modelId) {
                $this->activateParserTarget($source, $brandId, $modelId, $entry['url']);
                $matchedCount++;

                continue;
            }

            if ($this->looksLikeJunkName($entry['brand_name']) || $this->looksLikeJunkName($entry['model_name'])) {
                // Viloyat, sahifalash, bo'sh nom kabi "chiqindi" — bu Muharrir
                // vaqtini olmasligi uchun kutish navbatiga ham qo'shilmaydi.
                $skippedJunkCount++;

                continue;
            }

            $this->upsertPendingCandidate($source, $entry);
            $unmatchedCount++;
        }

        return ['matched' => $matchedCount, 'unmatched' => $unmatchedCount, 'skipped_junk' => $skippedJunkCount];
    }

    /**
     * Kutish navbatidagi vizual duplikatlarni (masalan "A5" va "А5")
     * bitta yozuvga birlashtiradi — ikkalasini ham alohida saqlab, Muharrirni
     * ikki marta bir xil ishni ko'rishga majburlamaslik uchun. Bu HECH QANDAY
     * katalog yozuvi yaratmaydi, faqat kutish jadvalining o'zini tozalaydi.
     *
     * @return array{merged: int}
     */
    public function deduplicatePendingCandidates(): array
    {
        $pending = UnmatchedBrandModelCandidate::where('status', 'pending')->get();
        $seen = [];
        $mergedCount = 0;

        foreach ($pending as $candidate) {
            $key = $candidate->source_id.'|'
                .$this->normalizeForMatching($candidate->brand_name_raw).'|'
                .$this->normalizeForMatching($candidate->model_name_raw);

            if (isset($seen[$key])) {
                // Bu allaqachon ko'rilgan (vizual duplikat) — takroriy qatorni
                // o'chiramiz, faqat birinchisini qoldiramiz.
                $candidate->delete();
                $mergedCount++;

                continue;
            }

            $seen[$key] = true;
        }

        return ['merged' => $mergedCount];
    }

    private function upsertPendingCandidate(Source $source, array $entry): void
    {
        // updateOrCreate() ishlatilmaydi — chunki u first_seen_at'ni
        // qayta-qayta yozib qo'yishi (yoki umuman to'ldirmasligi) mumkin edi.
        // Yozuv birinchi marta yaratilganda first_seen_at hozirgi vaqt bilan
        // to'ldiriladi va keyin hech qachon o'zgartirilmaydi; last_seen_at
        // esa har safar yangilanadi.
        $candidate = UnmatchedBrandModelCandidate::firstOrNew([
            'source_id' => $source->id,
            'brand_name_raw' => $entry['brand_name'],
            'model_name_raw' => $entry['model_name'],
        ]);

        if (! $candidate->exists) {
            $candidate->first_seen_at = now();
        }

        $candidate->discovered_url = $entry['url'];
        $candidate->status = 'pending';
        $candidate->last_seen_at = now();
        $candidate->save();
    }

    private function normalizeForMatching(string $name): string
    {
        $name = trim($name);
        $name = strtr($name, self::CYRILLIC_TO_LATIN_MAP);
        $name = mb_strtolower($name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function looksLikeJunkName(string $name): bool
    {
        $trimmed = trim($name);

        if (mb_strlen($trimmed) < 2) {
            return true;
        }

        if (! preg_match('/[\p{L}\p{N}]/u', $trimmed)) {
            return true;
        }

        $lower = mb_strtolower($trimmed);

        foreach (self::REJECT_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function activateParserTarget(Source $source, int $brandId, int $modelId, string $url): void
    {
        ParserTarget::updateOrCreate(
            [
                'source_id' => $source->id,
                'model_id' => $modelId,
            ],
            [
                'brand_id' => $brandId,
                'target_url' => $url,
                'is_active' => true,
            ]
        );
    }
}
