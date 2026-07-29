<?php

namespace App\Exceptions;

/**
 * TZ bo'lim 8.7 (mashina kodlari): unmatched_brand / unmatched_model.
 * Ingestion job bu exception'ni ushlab, IngestionItemError'ga to'g'ri
 * "code" bilan yozadi — umumiy "processing_error" o'rniga.
 */
class UnmatchedCatalogEntityException extends \RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
