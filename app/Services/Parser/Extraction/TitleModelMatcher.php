<?php

namespace App\Services\Parser\Extraction;

/**
 * Kartochka sarlavhasi kutilgan model'ga mos kelishini tekshiradi.
 *
 * MUHIM: eski versiyada fuzzy tekshiruv butun (uzun) sarlavhani qisqa model
 * nomi bilan solishtirardi (similar_text/levenshtein) — bu deyarli hech qachon
 * ishlamas, faqat aniq substring mos kelardi. Natijada Kiril yoki xato yozilgan
 * ("Джентра", "Kobalt", "Спарк") HAQIQIY e'lonlar noto'g'ri rad etilardi.
 *
 * Yangi versiya TOKEN darajasida ishlaydi: sarlavhani so'zlarga bo'lib, har bir
 * so'z(lar) oynasi model varianti bilan uzunlikka mos fuzzy chegara bilan
 * solishtiriladi. Bu haqiqiy imlo xatolarini ushlaydi, lekin butunlay boshqa
 * mashinalarni (masalan "Luaz" vs "Simbir") qabul qilmaydi.
 */
class TitleModelMatcher
{
    public function matches(string $titleText, string $expectedModel): bool
    {
        if (trim($titleText) === '' || trim($expectedModel) === '') {
            return true;
        }

        $title = $this->normalize($titleText);
        $model = $this->normalize($expectedModel);

        if ($model === '') {
            return true;
        }

        // 1) To'g'ridan-to'g'ri substring — eng ishonchli.
        if (str_contains($title, $model)) {
            return true;
        }

        $titleTokens = array_values(array_filter(explode(' ', $title), fn ($t) => $t !== ''));

        foreach ($this->buildVariants($model) as $variant) {
            $variant = trim($variant);

            if ($variant === '') {
                continue;
            }

            if (str_contains($title, $variant)) {
                return true;
            }

            // 2) Ko'p so'zli model (masalan "nexia 3") — sarlavhadagi ketma-ket
            //    so'zlar oynasini variant bilan fuzzy solishtiramiz.
            $variantTokens = array_values(array_filter(explode(' ', $variant), fn ($t) => $t !== ''));
            $windowSize = count($variantTokens);

            if ($windowSize < 1) {
                continue;
            }

            $limit = count($titleTokens) - $windowSize;

            for ($i = 0; $i <= $limit; $i++) {
                $window = implode(' ', array_slice($titleTokens, $i, $windowSize));

                if ($this->tokenFuzzyEquals($window, $variant)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Ikki (qisqa) qatorni uzunlikka mos fuzzy chegara bilan solishtiradi.
     */
    private function tokenFuzzyEquals(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $length = max(mb_strlen($a), mb_strlen($b));

        // Juda qisqa model kodlari (<=3 harf, masalan "x5", "cls") uchun fuzzy
        // XAVFLI — bir harf farq ham boshqa modelga aylantiradi. Aniq mos talab.
        if ($length <= 3) {
            return false;
        }

        $threshold = $length <= 6 ? 1 : 2;

        return levenshtein($a, $b) <= $threshold;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);

        $map = [
            'қ' => 'q', 'ғ' => 'g', 'ў' => 'o', 'ҳ' => 'h',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'j', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'x', 'ц' => 's', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'i', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private function buildVariants(string $model): array
    {
        return array_values(array_unique([
            $model,
            str_replace('chevrolet ', '', $model),
            str_replace('daewoo ', '', $model),
            str_replace('ravon ', '', $model),
            str_replace(' ', '', $model),
        ]));
    }
}
