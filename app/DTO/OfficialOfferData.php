<?php

namespace App\DTO;

final class OfficialOfferData
{
    public function __construct(
        public readonly int $sourceId,
        public readonly string $externalId,
        public readonly string $sourceUrl,
        public readonly ?string $brandRaw,
        public readonly ?string $modelRaw,
        public readonly ?string $trimName,
        public readonly ?int $year,
        public readonly int $priceAmount,
        public readonly string $currency,
        public readonly \DateTimeImmutable $observedAt,
        public readonly ?\DateTimeImmutable $validFrom,
        public readonly ?\DateTimeImmutable $validTo,
        public readonly string $contentHash,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sourceId: (int) $data['source_id'],
            externalId: (string) $data['external_id'],
            sourceUrl: (string) $data['url'],
            brandRaw: $data['brand'] ?? null,
            modelRaw: $data['model'] ?? null,
            trimName: $data['trim'] ?? null,
            year: isset($data['year']) ? (int) $data['year'] : null,
            priceAmount: (int) $data['price']['amount'],
            currency: (string) ($data['price']['currency'] ?? 'UZS'),
            observedAt: new \DateTimeImmutable($data['observed_at']),
            validFrom: isset($data['valid_from']) ? new \DateTimeImmutable($data['valid_from']) : null,
            validTo: isset($data['valid_to']) ? new \DateTimeImmutable($data['valid_to']) : null,
            contentHash: (string) $data['content_hash'],
        );
    }
}
