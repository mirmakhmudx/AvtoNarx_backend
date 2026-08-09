<?php

namespace App\DTO;

final class ListingData
{
    public function __construct(
        public readonly int $sourceId,
        public readonly string $externalId,
        public readonly string $canonicalUrl,
        public readonly ?string $brandRaw,
        public readonly ?string $modelRaw,
        public readonly ?int $year,
        public readonly int $priceAmount,
        public readonly string $currency,
        public readonly string $condition,
        public readonly string $sellerType,
        public readonly ?string $region,
        public readonly ?string $city,
        public readonly ?\DateTimeImmutable $sourcePublishedAt,
        // Agar parser allaqachon parser_target orqali qaysi brand/model
        // ekanini aniq bilsa (masalan OlxUzAdapter — sahifa o'zi
        // ParserTarget.brand_id/model_id bilan bog'langan), shu ID'lar
        // to'g'ridan-to'g'ri beriladi va alias orqali qayta izlanmaydi.
        // Tashqi HTTP Ingestion API orqali kelgan ma'lumotda bular hech
        // qachon berilmaydi (parser bizning ichki ID'larimizni bilmaydi),
        // o'sha holatda har doim brandRaw/modelRaw orqali alias izlanadi.
        public readonly ?int $knownBrandId = null,
        public readonly ?int $knownModelId = null,
        public readonly ?string $contentHash = null,

    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sourceId: (int) $data['source_id'],
            externalId: (string) $data['external_id'],
            canonicalUrl: (string) $data['canonical_url'],
            brandRaw: $data['brand_raw'] ?? null,
            modelRaw: $data['model_raw'] ?? null,
            year: isset($data['year']) ? (int) $data['year'] : null,
            priceAmount: (int) $data['price_amount'],
            currency: (string) ($data['currency'] ?? 'UZS'),
            condition: (string) ($data['condition'] ?? 'unknown'),
            sellerType: (string) ($data['seller_type'] ?? 'unknown'),
            region: $data['region'] ?? null,
            city: $data['city'] ?? null,
            sourcePublishedAt: isset($data['source_published_at']) ? new \DateTimeImmutable($data['source_published_at']) : null,
            knownBrandId: isset($data['known_brand_id']) ? (int) $data['known_brand_id'] : null,
            knownModelId: isset($data['known_model_id']) ? (int) $data['known_model_id'] : null,
            contentHash: isset($data['content_hash']) ? (string) $data['content_hash'] : null,
        );
    }

    public function computeContentHash(): string
    {
        return hash('sha256', implode('|', [
            $this->sourceId,
            $this->externalId,
            $this->canonicalUrl,
            $this->brandRaw,
            $this->modelRaw,
            $this->year,
            $this->priceAmount,
            $this->currency,
            $this->condition,
        ]));
    }
}
