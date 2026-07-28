<?php

namespace App\Services\Parser\Contracts;

interface SourceAdapterInterface
{
    public function sourceCode(): string;

    /**
     * Ruxsat etilgan katalog sahifalarini qaytaradi (masalan brand/model bo'yicha URL'lar).
     *
     * @return array<int, string>
     */
    public function discoverTargets(): array;

    /**
     * Bitta sahifadan kartochkalarni chiqaradi.
     *
     * @return array<int, array{item: array|null, rejected_reason: string|null}>
     */
    public function extractFromUrl(string $url): array;
}
