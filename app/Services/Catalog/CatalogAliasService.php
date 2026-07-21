<?php

namespace App\Services\Catalog;

use App\Enums\EntityType;
use App\Models\CatalogAlias;

class CatalogAliasService
{
    public function resolve(EntityType $entityType, string $rawName, ?int $sourceId = null): ?int
    {
        $normalized = CatalogAlias::normalize($rawName);

        if ($sourceId !== null) {
            $alias = CatalogAlias::query()
                ->where('entity_type', $entityType->value)
                ->where('source_id', $sourceId)
                ->where('normalized_alias', $normalized)
                ->where('is_verified', true)
                ->first();

            if ($alias) {
                return $alias->entity_id;
            }
        }

        $alias = CatalogAlias::query()
            ->where('entity_type', $entityType->value)
            ->whereNull('source_id')
            ->where('normalized_alias', $normalized)
            ->where('is_verified', true)
            ->first();

        return $alias?->entity_id;
    }

    public function createPendingAlias(
        EntityType $entityType,
        int $entityId,
        string $rawName,
        ?int $sourceId = null,
    ): CatalogAlias {
        return CatalogAlias::updateOrCreate(
            [
                'entity_type' => $entityType->value,
                'source_id' => $sourceId,
                'normalized_alias' => CatalogAlias::normalize($rawName),
            ],
            [
                'entity_id' => $entityId,
                'alias' => $rawName,
                'is_verified' => false,
            ]
        );
    }

    public function verify(CatalogAlias $alias): CatalogAlias
    {
        $alias->update(['is_verified' => true]);

        return $alias;
    }
}
