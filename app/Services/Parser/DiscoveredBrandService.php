<?php

namespace App\Services\Parser;

use App\Enums\EntityType;
use App\Models\Brand;
use App\Models\DiscoveredBrand;
use App\Services\Catalog\CatalogAliasService;
use Illuminate\Support\Str;

class DiscoveredBrandService
{
    public function __construct(
        private readonly CatalogAliasService $aliasService,
    ) {}

    /**
     * Admin tasdiqlagach: mavjud markaga bog'laydi (agar $existingBrandId berilgan
     * bo'lsa) yoki yangi Brand yaratadi, so'ng alias'ni tasdiqlaydi va
     * discovered_brands yozuvini o'chiradi (u endi "hal qilingan").
     */
    public function resolve(
        DiscoveredBrand $discovered,
        ?int $existingBrandId = null,
        ?string $name = null,
        ?string $slug = null,
        ?string $countryCode = null,
    ): Brand {
        if ($existingBrandId !== null) {
            $brand = Brand::findOrFail($existingBrandId);
        } else {
            $brand = Brand::create([
                'name' => $name ?? $discovered->name,
                'slug' => $slug ?? Str::slug($name ?? $discovered->name),
                'country_code' => $countryCode,
                'is_active' => true,
            ]);
        }

        $alias = $this->aliasService->createPendingAlias(
            EntityType::Brand,
            $brand->id,
            $discovered->name,
            $discovered->source_id,
        );
        $this->aliasService->verify($alias);

        $discovered->delete();

        return $brand;
    }

    public function ignore(DiscoveredBrand $discovered): void
    {
        $discovered->delete();
    }

    /**
     * @param  array<int>  $ids
     */
    public function bulkIgnore(array $ids): int
    {
        return DiscoveredBrand::whereIn('id', $ids)->delete();
    }
}
