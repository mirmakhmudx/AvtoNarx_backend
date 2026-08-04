<?php

namespace App\Services\Parser\Extraction;

/**
 * E'lon kartochkasi sarlavhasi kutilayotgan model nomini o'zida
 * saqlaydimi — tekshiradi.
 *
 * Nega kerak: OLX (va boshqa marketplace'lar) ba'zan target sahifasida
 * (masalan "Daewoo Tacuma" qidiruv natijasi) butunlay BOSHQA modelni
 * (masalan "Daewoo Matiz") ko'rsatadi — hech qanday "extended_search"
 * belgisisiz. Bunday holatni faqat URL marker orqali ushlab bo'lmaydi,
 * shuning uchun kartochkaning haqiqiy sarlavhasi bilan mustaqil
 * solishtirish kerak.
 *
 * Solishtirish ataylab "yumshoq": katta/kichik harf, tinish belgilari va
 * bo'shliqlar farqi e'tiborga olinmaydi (masalan "Daewoo-Tacuma" va
 * "daewoo tacuma" bir xil deb hisoblanadi), chunki manba sarlavhalari
 * format jihatidan bir xil emas.
 */
class TitleModelMatcher
{
    /**
     * @param  string  $titleText  Kartochkadan olingan xom sarlavha matni.
     * @param  string  $expectedModelName  Target'ning kutilayotgan model nomi (ma'lumotnomadan).
     * @return bool  true — sarlavha model nomini o'z ichiga oladi (yoki
     *               tekshirish imkonsiz — masalan bo'sh sarlavha/model,
     *               bu holatda "mos" deb hisoblanadi, chunki bunday
     *               hollar boshqa qoidalar bilan alohida ko'rib chiqiladi).
     */
    public function matches(string $titleText, string $expectedModelName): bool
    {
        if (trim($titleText) === '') {
            return true;
        }

        $normalizedTitle = $this->normalize($titleText);
        $normalizedModel = $this->normalize($expectedModelName);

        if ($normalizedModel === '') {
            return true;
        }

        return str_contains($normalizedTitle, $normalizedModel);
    }

    /**
     * Matnni kichik harflarga o'tkazadi va harf/raqamdan boshqa hamma
     * narsani (bo'shliq, tire, tinish belgilari) olib tashlaydi. Shunda
     * "Daewoo Tacuma", "daewoo-tacuma" va "DAEWOO TACUMA," bir xil
     * satrga aylanadi.
     */
    private function normalize(string $value): string
    {
        $lower = mb_strtolower($value);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $lower);
    }
}
