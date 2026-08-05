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
 * IKKI QO'SHIMCHA MUAMMO (real e'lonlarni ko'rib aniqlandi):
 *  1) Ko'p e'lonlar KIRILL alifbosida yoziladi ("Дамас"), lekin bizning
 *     katalogdagi model nomi lotin alifbosida ("Damas") — oddiy satr
 *     solishtirish bularni HECH QACHON bir xil deb topmaydi. Shuning
 *     uchun solishtirishdan oldin kirillchani lotinchaga o'giramiz.
 *  2) Odamlar ko'pincha model nomini xato yozadi ("Damas" o'rniga
 *     "Damaz"). Shuning uchun aniq mos kelish topilmasa, 1-2 harf
 *     farqiga toqat qiladigan "yumshoq" qidiruv ham qo'shildi.
 *
 * Solishtirish ataylab "yumshoq": katta/kichik harf, tinish belgilari va
 * bo'shliqlar farqi e'tiborga olinmaydi.
 */
class TitleModelMatcher
{
    /**
     * Rus/o'zbek kirill harflarini lotin harflariga o'giradi — shunda
     * "Дамас" va "Damas" solishtirishda bir xil satrga aylanadi.
     *
     * @var array<string, string>
     */
    private const CYRILLIC_TO_LATIN = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'yo', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'x', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'i', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        // O'zbek tiliga xos kirill harflari:
        'ў' => 'o', 'қ' => 'q', 'ғ' => 'g', 'ҳ' => 'h', 'і' => 'i',
    );

    // Bu uzunlikdan qisqa model nomlari uchun "yumshoq" (Levenshtein)
    // qidiruv o'chiriladi — juda qisqa nomlarda (masalan "M5") tasodifiy
    // soxta moslik topilish xavfi yuqori.
    private const MIN_LENGTH_FOR_FUZZY_MATCH = 4;

    /**
     * @param  string  $titleText  Kartochkadan olingan xom sarlavha matni.
     * @param  string  $expectedModelName  Target'ning kutilayotgan model nomi (ma'lumotnomadan).
     * @return bool  true — sarlavha model nomini o'z ichiga oladi (aniq yoki
     *               yumshoq moslik bilan), yoki tekshirish imkonsiz (bo'sh
     *               sarlavha/model — bunday hollar boshqa qoidalar bilan
     *               alohida ko'rib chiqiladi).
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

        if (str_contains($normalizedTitle, $normalizedModel)) {
            return true;
        }

        return $this->fuzzyContains($normalizedTitle, $normalizedModel);
    }

    /**
     * Aniq moslik topilmagan hollar uchun — sarlavha ichida model nomiga
     * juda yaqin (1-2 harf farqli) qism bormi, tekshiradi. Masalan
     * "damaz" satrida "damas"ga (1 harf farq) juda yaqin bo'lak bor.
     */
    private function fuzzyContains(string $haystack, string $needle): bool
    {
        $needleLen = mb_strlen($needle);

        if ($needleLen < self::MIN_LENGTH_FOR_FUZZY_MATCH) {
            return false;
        }

        // Model nomi qanchalik uzun bo'lsa, shunchalik ko'proq harf
        // farqiga yo'l qo'yamiz — lekin juda ko'p emas (soxta moslikning
        // oldini olish uchun).
        $maxDistance = $needleLen <= 6 ? 1 : 2;

        $haystackLen = mb_strlen($haystack);

        for ($start = 0; $start < $haystackLen; $start++) {
            foreach (array($needleLen - 1, $needleLen, $needleLen + 1) as $windowLen) {
                if ($windowLen < 1 || $start + $windowLen > $haystackLen) {
                    continue;
                }

                $window = mb_substr($haystack, $start, $windowLen);

                if (levenshtein($window, $needle) <= $maxDistance) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Matnni kichik harflarga o'tkazadi, kirillchani lotinchaga o'giradi,
     * so'ng harf/raqamdan boshqa hamma narsani (bo'shliq, tire, tinish
     * belgilari) olib tashlaydi.
     */
    private function normalize(string $value): string
    {
        $lower = mb_strtolower($value);
        $transliterated = strtr($lower, self::CYRILLIC_TO_LATIN);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $transliterated);
    }
}
