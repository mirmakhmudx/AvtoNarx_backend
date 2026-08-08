<?php

namespace App\Console\Commands;

use App\Enums\EntityType;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\CatalogAlias;
use App\Models\ParserTarget;
use App\Models\UnmatchedBrandModelCandidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogPromoteCandidatesCommand extends Command
{
    protected $signature = 'catalog:promote-candidates
        {ids* : unmatched_brand_model_candidates.id qiymatlari (bir nechta, bo\'sh joy bilan)}
        {--dry-run : Bazaga hech narsa yozmasdan, nima qilinishini ko\'rsatish}';

    protected $description = 'Tanlangan unmatched nomzodlarni haqiqiy katalogga aylantiradi: brand/model yaratadi, tasdiqlangan alias yozadi va parser_target ochadi';

    private const DRY_RUN_ABORT = 'CATALOG_PROMOTE_DRY_RUN_ABORT';

    public function handle(): int
    {
        $ids = collect($this->argument('ids'))->map(fn ($id) => (int) $id)->filter()->unique();

        if ($ids->isEmpty()) {
            $this->error('Kamida bitta ID kiriting.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('=== DRY-RUN rejimi: hech narsa bazaga yozilmaydi ===');
        }

        $promoted = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $candidate = UnmatchedBrandModelCandidate::find($id);

            if (! $candidate) {
                $this->warn("#{$id}: topilmadi, o'tkazib yuborildi.");
                $skipped++;

                continue;
            }

            if ($candidate->status !== 'pending') {
                $this->warn("#{$id}: status '{$candidate->status}' (pending emas), o'tkazib yuborildi.");
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($candidate, $dryRun) {
                    $this->promoteOne($candidate);

                    if ($dryRun) {
                        // Tranzaksiyani atayin bekor qilamiz, hech narsa saqlanmaydi
                        throw new \RuntimeException(self::DRY_RUN_ABORT);
                    }
                });

                $prefix = $dryRun ? '[DRY-RUN] ' : '';
                $this->info("{$prefix}#{$candidate->id}: {$candidate->brand_name_raw} / {$candidate->model_name_raw} -> PROMOTE QILINDI");
                $promoted++;
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === self::DRY_RUN_ABORT) {
                    $this->info("[DRY-RUN] #{$candidate->id}: {$candidate->brand_name_raw} / {$candidate->model_name_raw} -> promote qilingan BO'LARDI (rollback qilindi)");
                    $promoted++;

                    continue;
                }

                $this->error("#{$candidate->id}: xato - ".$e->getMessage());
                $skipped++;
            } catch (\Throwable $e) {
                $this->error("#{$candidate->id}: xato - ".$e->getMessage());
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Jami: {$promoted} ta ".($dryRun ? 'promote qilingan BO\'LARDI' : 'promote qilindi').", {$skipped} ta o'tkazib yuborildi.");

        return self::SUCCESS;
    }

    private function promoteOne(UnmatchedBrandModelCandidate $candidate): void
    {
        $sourceId = $candidate->source_id;
        $brandName = trim($candidate->brand_name_raw);
        $modelName = trim($candidate->model_name_raw);

        $brand = Brand::where('slug', Str::slug($brandName))->first();

        if (! $brand) {
            $brand = Brand::create([
                'name' => $brandName,
                'slug' => Str::slug($brandName),
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        $modelSlug = Str::slug($modelName);

        $carModel = CarModel::where('brand_id', $brand->id)
            ->where('slug', $modelSlug)
            ->first();

        if (! $carModel) {
            $carModel = CarModel::create([
                'brand_id' => $brand->id,
                'name' => $modelName,
                'slug' => $modelSlug,
                'is_active' => true,
            ]);
        }

        // Manbaga bog'langan tasdiqlangan alias (masalan olx_uz uchun)
        $this->upsertVerifiedAlias(EntityType::Brand, $brand->id, $brandName, $sourceId);
        $this->upsertVerifiedAlias(EntityType::Model, $carModel->id, $modelName, $sourceId);

        // Global (source_id = null) tasdiqlangan alias — kelajakda boshqa manbalar
        // (masalan olx.uz dan tashqari yangi parser) ham shu nomni tanisin.
        $this->upsertVerifiedAlias(EntityType::Brand, $brand->id, $brandName, null);
        $this->upsertVerifiedAlias(EntityType::Model, $carModel->id, $modelName, null);

        ParserTarget::updateOrCreate(
            [
                'source_id' => $sourceId,
                'model_id' => $carModel->id,
            ],
            [
                'brand_id' => $brand->id,
                'target_url' => $candidate->discovered_url,
                'is_active' => true,
            ]
        );

        $candidate->update(['status' => 'resolved']);
    }

    private function upsertVerifiedAlias(EntityType $entityType, int $entityId, string $rawName, ?int $sourceId): void
    {
        CatalogAlias::updateOrCreate(
            [
                'entity_type' => $entityType->value,
                'source_id' => $sourceId,
                'normalized_alias' => CatalogAlias::normalize($rawName),
            ],
            [
                'entity_id' => $entityId,
                'alias' => $rawName,
                'is_verified' => true,
            ]
        );
    }
}
