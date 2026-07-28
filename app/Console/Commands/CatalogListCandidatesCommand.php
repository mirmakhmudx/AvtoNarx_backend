<?php

namespace App\Console\Commands;

use App\Models\UnmatchedBrandModelCandidate;
use Illuminate\Console\Command;

class CatalogListCandidatesCommand extends Command
{
    protected $signature = 'catalog:candidates
        {--brand= : Brand nomi boyicha filter (ILIKE substring, masalan --brand=chevrolet)}
        {--model= : Model nomi boyicha filter (ILIKE substring)}
        {--status=pending : Status boyicha filter (pending, resolved, ignored, yoki "all")}
        {--limit=100 : Chiqariladigan qatorlar soni}';

    protected $description = 'unmatched_brand_model_candidates jadvalidan yozuvlarni ID bilan chiqaradi (promote qilishdan oldin tanlash uchun)';

    public function handle(): int
    {
        $query = UnmatchedBrandModelCandidate::query()->orderBy('brand_name_raw')->orderBy('model_name_raw');

        if ($this->option('status') !== 'all') {
            $query->where('status', $this->option('status'));
        }

        if ($brand = $this->option('brand')) {
            $query->where('brand_name_raw', 'ILIKE', '%' . $brand . '%');
        }

        if ($model = $this->option('model')) {
            $query->where('model_name_raw', 'ILIKE', '%' . $model . '%');
        }

        $rows = $query->limit((int) $this->option('limit'))->get(['id', 'brand_name_raw', 'model_name_raw', 'status']);

        if ($rows->isEmpty()) {
            $this->warn('Hech qanday yozuv topilmadi.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Brand', 'Model', 'Status'],
            $rows->map(fn ($r) => [$r->id, $r->brand_name_raw, $r->model_name_raw, $r->status])->all()
        );

        $this->info('Jami: ' . $rows->count() . ' qator ko\'rsatildi.');
        $this->comment('Promote qilish uchun: php artisan catalog:promote-candidates ' . $rows->pluck('id')->take(5)->implode(' ') . ' ...');

        return self::SUCCESS;
    }
}
