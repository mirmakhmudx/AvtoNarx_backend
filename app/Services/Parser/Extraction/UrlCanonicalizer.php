<?php

namespace App\Services\Parser\Extraction;

class UrlCanonicalizer
{
    public function canonicalize(string $baseUrl, string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $url = $href;
        } else {
            $url = rtrim($baseUrl, '/').'/'.ltrim($href, '/');
        }

        // Query parametrlarni olib tashlaymiz (allowlist bo'lmasa hammasi kesiladi)
        $parts = parse_url($url);

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';

        return $scheme.'://'.$host.$path;
    }
}
