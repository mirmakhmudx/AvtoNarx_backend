<?php

namespace App\Services\Parser\Extraction;

class ContentHashBuilder
{
    public function build(
        string $source,
        string $externalId,
        string $canonicalUrl,
        string $brand,
        string $model,
        ?int $year,
        int $priceAmount,
        string $currency,
        string $condition,
    ): string {
        $data = [
            'brand' => trim($brand),
            'canonical_url' => $canonicalUrl,
            'condition' => $condition,
            'currency' => $currency,
            'external_id' => $externalId,
            'model' => trim($model),
            'price_amount' => $priceAmount,
            'source' => $source,
            'year' => $year,
        ];

        ksort($data);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json);
    }
}
