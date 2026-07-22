<?php

namespace App\Services\Parser\Extraction;

class ExternalIdBuilder
{
    /**
     * Ustuvorlik: 1) manbaning aniq ID'si (masalan ID bilan boshlangan segment),
     * 2) canonical URL'dan olingan ID, 3) canonical URL'ning SHA-256 hash'i.
     */
    public function build(string $canonicalUrl): string
    {
        if (preg_match('/-ID([A-Za-z0-9]+)\.html$/', $canonicalUrl, $matches)) {
            return 'olx-' . $matches[1];
        }

        $pathId = basename(parse_url($canonicalUrl, PHP_URL_PATH) ?? '');

        if ($pathId !== '') {
            return 'olx-' . preg_replace('/\.\w+$/', '', $pathId);
        }

        return 'olx-' . hash('sha256', $canonicalUrl);
    }
}
