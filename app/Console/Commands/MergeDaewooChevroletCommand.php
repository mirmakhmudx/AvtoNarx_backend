<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\CatalogAlias;
use App\Models\MarketListing;
use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class MergeDaewooChevroletCommand extends Command
{
    protected $signature = 'catalog:merge-daewoo-chevrolet {--dry-run : Faqat ko\'rsatadi, o\'zgartirmaydi}';

    protected $description = 'Daewoo↔Chevrolet dublikat modellarini Chevrolet\'ga birlashtiradi.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $daewoo = Brand::where('name', 'Daewoo')->first();
        $chevrolet = Brand::where('name', 'Chevrolet')->first();

        if (! $daewoo || ! $chevrolet) {
            $this->error('Daewoo yoki Chevrolet brendi topilmadi.');

            return self::FAILURE;
        }

        $daewooModels = CarModel::where('brand_id', $daewoo->id)->get();
        $merged = 0;
        $skipped = 0;

        foreach ($daewooModels as $from) {
            $to = CarModel::where('brand_id', $chevrolet->id)
                ->whereRaw('lower(name) = ?', [mb_strtolower($from->name)])
                ->first();

            if (! $to) {
                $skipped++;

                continue;
            }

            $fromListings = MarketListing::where('model_id', $from->id)->count();

            $this->line(sprintf(
                '%s Daewoo %s (#%d, e\'lon=%d) -> Chevrolet %s (#%d)',
                $dry ? '[DRY]' : '➤',
                $from->name,
                $from->id,
                $fromListings,
                $to->name,
                $to->id,
            ));

            if ($dry) {
                $merged++;

                continue;
            }

            $this->mergeModel($from->id, $to->id, $to->brand_id);
            $merged++;
        }

        $this->newLine();
        $this->info(($dry ? '[DRY-RUN] ' : '')."Birlashtirildi: {$merged}, tegilmadi: {$skipped}");

        if ($dry) {
            $this->comment('Bu faqat ko\'rsatuv edi. Haqiqiy birlashtirish uchun --dry-run\'siz ishga tushiring.');
        }

        return self::SUCCESS;
    }

    private function mergeModel(int $fromId, int $toId, int $toBrandId): void
    {
        DB::transaction(function () use ($fromId, $toId, $toBrandId) {
            MarketListing::where('model_id', $fromId)
                ->update(['model_id' => $toId, 'brand_id' => $toBrandId]);

            MarketPriceStatistic::where('model_id', $fromId)
                ->update(['model_id' => $toId, 'brand_id' => $toBrandId]);

            OfficialOffer::where('model_id', $fromId)
                ->update(['model_id' => $toId, 'brand_id' => $toBrandId]);

            $aliases = CatalogAlias::where('entity_type', 'model')
                ->where('entity_id', $fromId)->get();

            foreach ($aliases as $alias) {
                $conflict = CatalogAlias::where('entity_type', 'model')
                    ->where('entity_id', $toId)
                    ->where('normalized_alias', $alias->normalized_alias)
                    ->where(function ($q) use ($alias) {
                        $q->where('source_id', $alias->source_id);
                        if ($alias->source_id === null) {
                            $q->orWhereNull('source_id');
                        }
                    })
                    ->exists();

                $conflict ? $alias->delete() : $alias->update(['entity_id' => $toId]);
            }

            CarModel::where('id', $fromId)->delete();
        });
    }
}
