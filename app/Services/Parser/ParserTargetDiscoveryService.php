<?php

namespace App\Services\Parser;

use App\Enums\EntityType;
use App\Models\ParserTarget;
use App\Models\Source;
use App\Models\UnmatchedBrandModelCandidate;
use App\Services\Catalog\CatalogAliasService;

class ParserTargetDiscoveryService
{
    public function __construct(
        private readonly CatalogAliasService $aliasService,
    ) {
    }

    public function processDiscoveredCombinations(Source $source, array $discovered): array
    {
        $matchedCount = 0;
        $unmatchedCount = 0;

        foreach ($discovered as $entry) {
            $brandId = $this->aliasService->resolve(EntityType::Brand, $entry['brand_name'], $source->id);
            $modelId = $brandId ? $this->aliasService->resolve(EntityType::Model, $entry['model_name'], $source->id) : null;

            if ($brandId && $modelId) {
                ParserTarget::updateOrCreate(
                    array(
                        'source_id' => $source->id,
                        'model_id' => $modelId,
                    ),
                    array(
                        'brand_id' => $brandId,
                        'target_url' => $entry['url'],
                        'is_active' => true,
                    )
                );

                $matchedCount++;

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

        return array('matched' => $matchedCount, 'unmatched' => $unmatchedCount);
    }
}
