<?php

namespace App\Services\Parser\Contracts;

interface SourceAdapterInterface
{
    public function sourceCode(): string;

    public function discoverTargets(): array;

    public function extractFromUrl(string $url): array;

}
