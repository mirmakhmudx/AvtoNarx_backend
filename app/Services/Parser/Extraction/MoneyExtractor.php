<?php

namespace App\Services\Parser\Extraction;

class MoneyExtractor
{
    private const REJECT_PHRASES = [
        'договорная',
        'обмен',
        'бесплатно',
        'кредит',
        'месяц',
        'первоначальный взнос',
    ];

    /**
     * @return array{amount:int, currency:string}|null narx topilmasa yoki rad etilsa null
     */
    public function extract(string $rawText): ?array
    {
        $normalized = mb_strtolower(trim($rawText));

        foreach (self::REJECT_PHRASES as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return null;
            }
        }

        // NBSP va oddiy bo'shliqlarni olib tashlaymiz, raqamlarni ajratamiz
        $cleaned = str_replace(["\xC2\xA0", ' '], '', $rawText);

        if (preg_match('/(\d+)\s*\$/u', $rawText) || str_contains($cleaned, '$')) {
            if (preg_match('/(\d[\d\xC2\xA0 ]*\d|\d)\s*\$/u', $rawText, $matches)) {
                $digits = preg_replace('/[^\d]/u', '', $matches[1]);

                if ($digits === '') {
                    return null;
                }

                return ['amount' => (int) $digits, 'currency' => 'USD'];
            }
        }

        if (preg_match('/(\d[\d\xC2\xA0 ]*\d|\d)\s*сум/ui', $rawText, $matches)) {
            $digits = preg_replace('/[^\d]/u', '', $matches[1]);

            if ($digits === '') {
                return null;
            }

            return ['amount' => (int) $digits, 'currency' => 'UZS'];
        }

        return null;
    }
}
