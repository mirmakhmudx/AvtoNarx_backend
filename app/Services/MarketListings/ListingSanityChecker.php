<?php

namespace App\Services\MarketListings;

class ListingSanityChecker
{
    public const MIN_PLAUSIBLE_PRICE_UZS = 1_500_000;

    private const FALLBACK_URL_MARKER = 'reason=extended_search';


    public function check(?string $canonicalUrl, ?int $priceUzs): ?array
    {
        if ($canonicalUrl !== null && str_contains($canonicalUrl, self::FALLBACK_URL_MARKER)) {
            return [
                'code' => 'olx_fallback_result',
                'message' => "OLX'ning \"hech narsa topilmadi, o'xshashlarini ko'ring\" fallback natijasi — "
                    .'haqiqiy moslik emas (URL\'da '.self::FALLBACK_URL_MARKER.' belgisi bor).',
            ];
        }

        if ($priceUzs !== null && $priceUzs < self::MIN_PLAUSIBLE_PRICE_UZS) {
            return [
                'code' => 'implausible_price',
                'message' => 'Narx ('.number_format($priceUzs).' UZS) mashina uchun aqlga sig\'maydigan '
                    .'darajada past — chegara: '.number_format(self::MIN_PLAUSIBLE_PRICE_UZS).' UZS.',
            ];
        }

        return null;
    }
}
