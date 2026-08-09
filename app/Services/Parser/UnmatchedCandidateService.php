<?php

namespace App\Services\Parser;

use App\Enums\EntityType;
use App\Models\CarModel;
use App\Models\ParserTarget;
use App\Models\UnmatchedBrandModelCandidate;
use App\Services\Catalog\CatalogAliasService;
use Illuminate\Database\Eloquent\Collection;

class UnmatchedCandidateService
{
    public function __construct(
        private readonly CatalogAliasService $aliasService,
    ) {}

    /**
     * @return Collection<int, UnmatchedBrandModelCandidate>
     */
    public function listPending(?string $brandFilter = null): Collection
    {
        $query = UnmatchedBrandModelCandidate::query()
            ->where('status', 'pending')
            ->with('source');

        if ($brandFilter !== null) {
            $query->where('brand_name_raw', 'ILIKE', $brandFilter);
        }

        return $query->orderBy('brand_name_raw')
            ->orderBy('model_name_raw')
            ->get();
    }

    /**
     * Har bir marka bo'yicha nechta pending candidate borligini qaytaradi —
     * admin qaysi markadan boshlashni tanlashi uchun.
     *
     * @return array<int, array{brand: string, count: int}>
     */
    public function pendingCountsByBrand(): array
    {
        return UnmatchedBrandModelCandidate::query()
            ->where('status', 'pending')
            ->selectRaw('brand_name_raw as brand, count(*) as count')
            ->groupBy('brand_name_raw')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['brand' => $row->brand, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Admin tasdiqlagach: yangi CarModel yaratadi, alias'larni tasdiqlaydi
     * (brand va model uchun), parser_target yaratadi va candidate'ni "resolved" qiladi.
     */
    public function resolve(
        UnmatchedBrandModelCandidate $candidate,
        int $brandId,
        string $modelName,
        string $modelSlug,
        ?int $productionFrom = null,
    ): CarModel {
        $carModel = CarModel::create([
            'brand_id' => $brandId,
            'name' => $modelName,
            'slug' => $modelSlug,
            'production_from' => $productionFrom,
            'is_active' => true,
        ]);

        // Brand alias — agar hali tasdiqlanmagan bo'lsa, tasdiqlaymiz
        $brandAlias = $this->aliasService->createPendingAlias(
            EntityType::Brand,
            $brandId,
            $candidate->brand_name_raw,
            $candidate->source_id,
        );
        $this->aliasService->verify($brandAlias);

        // Model alias — yangi modelga bog'laymiz va darhol tasdiqlaymiz
        $modelAlias = $this->aliasService->createPendingAlias(
            EntityType::Model,
            $carModel->id,
            $candidate->model_name_raw,
            $candidate->source_id,
        );
        $this->aliasService->verify($modelAlias);

        // Parser target — endi shu model uchun ham narx yig'ila boshlaydi
        ParserTarget::updateOrCreate(
            [
                'source_id' => $candidate->source_id,
                'model_id' => $carModel->id,
            ],
            [
                'brand_id' => $brandId,
                'target_url' => $candidate->discovered_url,
                'is_active' => true,
            ]
        );

        $candidate->update(['status' => 'resolved']);

        return $carModel;
    }

    public function ignore(UnmatchedBrandModelCandidate $candidate): void
    {
        $candidate->update(['status' => 'ignored']);
    }

    /**
     * Bir nechta candidate'ni bittada e'tiborsiz qoldiradi.
     *
     * @param  array<int>  $ids
     */
    public function bulkIgnore(array $ids): int
    {
        return UnmatchedBrandModelCandidate::whereIn('id', $ids)
            ->where('status', 'pending')
            ->update(['status' => 'ignored']);
    }

    /**
     * Berilgan marka bo'yicha (yoki umuman barcha) qolgan hamma
     * "pending" candidate'larni bittada e'tiborsiz qoldiradi.
     * Diqqat: bu qaytarib bo'lmaydigan ommaviy amal.
     */
    public function ignoreAllPending(?string $brandFilter = null): int
    {
        $query = UnmatchedBrandModelCandidate::query()->where('status', 'pending');

        if ($brandFilter !== null) {
            $query->where('brand_name_raw', 'ILIKE', $brandFilter);
        }

        return $query->update(['status' => 'ignored']);
    }
}
