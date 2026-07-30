<?php

namespace App\Services\Parser;

use App\Enums\EntityType;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\DiscoveredBrand;
use App\Models\ParserTarget;
use App\Models\Source;
use App\Models\UnmatchedBrandModelCandidate;
use App\Services\Catalog\CatalogAliasService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class ParserTargetDiscoveryService
{
    /**
     * Model kodlarida (A5, X5, E200 kabi) uchraydigan, ko'rinishi bir xil
     * kirill/lotin harflar. Faqat shu holatlarda ikkalasi BIR XIL model
     * sifatida birlashtiriladi — umuman kirill nomlarni lotinga
     * "tarjima qilish" emas, faqat vizual duplikatlarni yo'qotish uchun.
     */
    private const CYRILLIC_TO_LATIN_MAP = array(
        'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M',
        'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X',
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'х' => 'x',
    );

    private const REJECT_KEYWORDS = array('область', 'каракалпакстан', 'другие', 'показать', 'все категории');

    public function __construct(
        private readonly CatalogAliasService $aliasService,
    ) {
    }

    /**
     * @return array{matched: int, auto_created: int, unmatched: int}
     */
    public function processDiscoveredCombinations(Source $source, array $discovered): array
    {
        $matchedCount = 0;
        $autoCreatedCount = 0;
        $unmatchedCount = 0;

        foreach ($discovered as $entry) {
            $brandId = $this->aliasService->resolve(EntityType::Brand, $entry['brand_name'], $source->id);
            $modelId = $brandId ? $this->aliasService->resolve(EntityType::Model, $entry['model_name'], $source->id) : null;

            if ($brandId && $modelId) {
                $this->activateParserTarget($source, $brandId, $modelId, $entry['url']);
                $matchedCount++;

                continue;
            }

            $autoResult = $this->tryAutoCreate($source, $entry, $brandId);

            if ($autoResult !== null) {
                $this->activateParserTarget($source, $autoResult['brand_id'], $autoResult['model_id'], $entry['url']);
                $autoCreatedCount++;

                continue;
            }

            UnmatchedBrandModelCandidate::updateOrCreate(
                array(
                    'source_id' => $source->id,
                    'brand_name_raw' => $entry['brand_name'],
                    'model_name_raw' => $entry['model_name'],
                ),
                array(
                    'discovered_url' => $entry['url'],
                    'status' => 'pending',
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                )
            );

            $unmatchedCount++;
        }

        return array('matched' => $matchedCount, 'auto_created' => $autoCreatedCount, 'unmatched' => $unmatchedCount);
    }

    /**
     * Hozirgi "pending" navbatidagi (avval, aqlli avtomatik mantiq
     * qo'shilishidan OLDIN yig'ilgan) yozuvlarni qayta ishlaydi — yangi HTTP
     * so'rovsiz, chunki brand/model/URL allaqachon saqlangan.
     *
     * @return array{auto_created: int, still_pending: int}
     */
    public function reprocessPendingCandidates(): array
    {
        $pending = UnmatchedBrandModelCandidate::where('status', 'pending')->with('source')->get();

        $autoCreated = 0;
        $stillPending = 0;

        foreach ($pending as $candidate) {
            $source = $candidate->source;

            if ($source === null) {
                $stillPending++;

                continue;
            }

            $entry = array(
                'brand_name' => $candidate->brand_name_raw,
                'model_name' => $candidate->model_name_raw,
                'url' => $candidate->discovered_url,
            );

            $brandId = $this->aliasService->resolve(EntityType::Brand, $entry['brand_name'], $source->id);
            $autoResult = $this->tryAutoCreate($source, $entry, $brandId);

            if ($autoResult !== null) {
                $this->activateParserTarget($source, $autoResult['brand_id'], $autoResult['model_id'], $entry['url']);
                $candidate->update(array('status' => 'resolved'));
                $autoCreated++;

                continue;
            }

            $stillPending++;
        }

        return array('auto_created' => $autoCreated, 'still_pending' => $stillPending);
    }

    /**
     * @return array{brand_id: int, model_id: int}|null
     */
    private function tryAutoCreate(Source $source, array $entry, ?int $existingBrandId): ?array
    {
        $brandId = $existingBrandId;

        if ($brandId === null) {
            $brandId = $this->tryAutoCreateBrand($source, $entry['brand_name']);

            if ($brandId === null) {
                return null;
            }
        }

        if ($this->looksLikeJunkName($entry['model_name'])) {
            return null;
        }

        $existingSimilar = $this->findSimilarModel($brandId, $entry['model_name']);

        if ($existingSimilar !== null) {
            $modelAlias = $this->aliasService->createPendingAlias(
                EntityType::Model,
                $existingSimilar->id,
                $entry['model_name'],
                $source->id,
            );
            $this->aliasService->verify($modelAlias);

            return array('brand_id' => $brandId, 'model_id' => $existingSimilar->id);
        }

        try {
            $carModel = CarModel::create(array(
                'brand_id' => $brandId,
                'name' => trim($entry['model_name']),
                'slug' => Str::slug($entry['model_name']) ?: Str::slug($entry['brand_name'] . '-' . $entry['model_name']),
                'is_active' => true,
            ));
        } catch (QueryException $e) {
            // Slug to'qnashuvi yoki boshqa DB cheklovi — xavfsizroq tomonga,
            // qo'lda ko'rib chiqish uchun qoldiramiz.
            return null;
        }

        $modelAlias = $this->aliasService->createPendingAlias(
            EntityType::Model,
            $carModel->id,
            $entry['model_name'],
            $source->id,
        );
        $this->aliasService->verify($modelAlias);

        return array('brand_id' => $brandId, 'model_id' => $carModel->id);
    }

    /**
     * Yangi brand'ni FAQAT u allaqachon "discovered_brands"da (ya'ni bizning
     * sifat filtridan — q- prefiks, viloyat nomlari va h.k. — o'tgan haqiqiy
     * OLX marka ro'yxatida) mavjud bo'lsagina avtomatik yaratamiz.
     */
    private function tryAutoCreateBrand(Source $source, string $brandNameRaw): ?int
    {
        if ($this->looksLikeJunkName($brandNameRaw)) {
            return null;
        }

        $discoveredBrand = $this->findDiscoveredBrandByName($source->id, $brandNameRaw);

        if ($discoveredBrand === null) {
            // Kashfiyot bosqichidan o'tmagan — bu qayerdandir boshqa yo'l
            // bilan kelgan, ehtiyot bo'lib qo'lda ko'rib chiqamiz.
            return null;
        }

        $brand = Brand::firstOrCreate(
            array('slug' => $discoveredBrand->slug),
            array('name' => $discoveredBrand->name, 'is_active' => true)
        );

        $brandAlias = $this->aliasService->createPendingAlias(
            EntityType::Brand,
            $brand->id,
            $brandNameRaw,
            $source->id,
        );
        $this->aliasService->verify($brandAlias);

        return $brand->id;
    }

    /**
     * DB darajasidagi LOWER() ga tayanmaydi — SQLite'ning standart LOWER()
     * funksiyasi faqat ASCII harflarni kichiklashtiradi, kirillcha nomlar
     * uchun ishlamaydi (masalan "УАЗ" o'zgarmay qoladi). Shuning uchun
     * solishtirishni PHP tarafida, mb_strtolower() bilan qilamiz — bu
     * SQLite'da ham, PostgreSQL'da ham bir xil, to'g'ri natija beradi.
     * Bitta source'dagi discovered_brands soni katta emas (bir necha yuzta),
     * shuning uchun xotirada solishtirish arzon.
     */
    private function findDiscoveredBrandByName(int $sourceId, string $brandNameRaw): ?DiscoveredBrand
    {
        $target = mb_strtolower(trim($brandNameRaw));

        return DiscoveredBrand::where('source_id', $sourceId)
            ->get()
            ->first(fn (DiscoveredBrand $discovered) => mb_strtolower(trim($discovered->name)) === $target);
    }

    /**
     * Kirill/lotin vizual duplikatlarini (masalan "A5" va "А5") bitta model
     * sifatida topish — shu brand ichida.
     */
    private function findSimilarModel(int $brandId, string $rawModelName): ?CarModel
    {
        $target = $this->normalizeForMatching($rawModelName);

        return CarModel::where('brand_id', $brandId)
            ->get()
            ->first(fn (CarModel $model) => $this->normalizeForMatching($model->name) === $target);
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

        // Kamida bitta harf YOKI raqam bo'lishi kerak — UAZ/VAZ/GAZ kabi
        // markalarda sof raqamli model nomlari (masalan "31512-010") ham
        // haqiqiy va keng tarqalgan, shuning uchun faqat harfni talab
        // qilish bunday holatlarni noto'g'ri rad etardi.
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
            array(
                'source_id' => $source->id,
                'model_id' => $modelId,
            ),
            array(
                'brand_id' => $brandId,
                'target_url' => $url,
                'is_active' => true,
            )
        );
    }
}
