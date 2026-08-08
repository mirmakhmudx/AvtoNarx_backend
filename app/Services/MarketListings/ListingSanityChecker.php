<?php

namespace App\Services\MarketListings;

/**
 * Bitta joyda saqlanadigan qoida: "aniq bo'lmagan mos kelish yoki mashina
 * uchun aqlga sig'maydigan narx bo'lsa — bazaga umuman yozilmasin".
 *
 * Ushbu klass uchta joyda ishlatiladi:
 *  - OlxUzAdapter (parser darajasida, eng erta bosqichda rad etish uchun);
 *  - ListingIngestionService (himoya qatlami — qaysi yo'l orqali kelishidan
 *    qat'i nazar, chunki ichki scraper ham, tashqi HTTP ingestion API ham
 *    shu servisni chaqiradi);
 *  - CleanupSuspiciousListingsCommand (bazadagi mavjud yozuvlarni retroaktiv
 *    tozalash uchun).
 *
 * Sabab shu uchtasini alohida-alohida yozish emas: qoida o'zgarganda
 * (masalan chegara narxi sozlanganda) faqat shu bitta fayl tahrirlanadi.
 */
class ListingSanityChecker
{
    /**
     * Mashina uchun aqlga sig'maydigan darajada past narx chegarasi (UZS).
     * Taxminan 100 AQSH dollari — bironta ham haqiqiy mashina e'loni bundan
     * past bo'lmaydi, lekin OLX'ning "extended_search" fallback natijalarida
     * uchraydigan soat, aksessuar va h.k. narxlari odatda shu chegaradan
     * ancha past bo'ladi.
     */
    public const MIN_PLAUSIBLE_PRICE_UZS = 1_500_000;

    /**
     * OLX o'zi ishlatadigan mexanizm: agar qidiruv/model bo'yicha e'lon kam
     * yoki umuman yo'q bo'lsa, "hech narsa topilmadi, mana shularga
     * o'xshashini ko'ring" rejimida BOSHQA (mos kelmaydigan) elonlarni
     * ko'rsatadi. Bunday sahifadagi kartochkalarning havolasida shu query
     * parametri bo'ladi.
     */
    private const FALLBACK_URL_MARKER = 'reason=extended_search';

    /**
     * @return array{code: string, message: string}|null null — muammo yo'q.
     */
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
