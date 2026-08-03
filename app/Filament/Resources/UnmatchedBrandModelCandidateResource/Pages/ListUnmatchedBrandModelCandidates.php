<?php

namespace App\Filament\Resources\UnmatchedBrandModelCandidateResource\Pages;

use App\Filament\Resources\UnmatchedBrandModelCandidateResource;
use Filament\Resources\Pages\ListRecords;

class ListUnmatchedBrandModelCandidates extends ListRecords
{
    protected static string $resource = UnmatchedBrandModelCandidateResource::class;

    protected function getHeaderActions(): array
    {
        // Qo'lda yaratish shart emas — yozuvlar faqat parser kashfiyoti
        // orqali paydo bo'ladi.
        return [];
    }
}
