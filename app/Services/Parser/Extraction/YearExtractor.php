<?php

namespace App\Services\Parser\Extraction;

class YearExtractor
{
    public function extract(string $rawText): ?int
    {
        $currentYear = (int) date('Y');
        $maxYear = $currentYear + 1;

        if (! preg_match_all('/\b(19[5-9]\d|20\d{2})\b/', $rawText, $matches)) {
            return null;
        }

        foreach ($matches[1] as $candidate) {
            $year = (int) $candidate;

            if ($year >= 1950 && $year <= $maxYear) {
                return $year;
            }
        }

        return null;
    }
}
