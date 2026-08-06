<?php

namespace App\Services\Parser\Extraction;

class TitleModelMatcher
{
    public function matches(string $titleText, string $expectedModel): bool
    {
        if (trim($titleText) === '' || trim($expectedModel) === '') {
            return true;
        }

        $title = $this->normalize($titleText);
        $model = $this->normalize($expectedModel);

        if (str_contains($title, $model)) {
            return true;
        }

        foreach ($this->buildVariants($model) as $variant) {

            if ($variant === '') {
                continue;
            }

            if (str_contains($title, $variant)) {
                return true;
            }

            similar_text($title, $variant, $percent);

            if ($percent >= 90) {
                return true;
            }

            if (levenshtein($title, $variant) <= 2) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);

        $map = [
            'қ'=>'q',
            'ғ'=>'g',
            'ў'=>'o',
            'ҳ'=>'h',

            'а'=>'a',
            'б'=>'b',
            'в'=>'v',
            'г'=>'g',
            'д'=>'d',
            'е'=>'e',
            'ё'=>'yo',
            'ж'=>'j',
            'з'=>'z',
            'и'=>'i',
            'й'=>'y',
            'к'=>'k',
            'л'=>'l',
            'м'=>'m',
            'н'=>'n',
            'о'=>'o',
            'п'=>'p',
            'р'=>'r',
            'с'=>'s',
            'т'=>'t',
            'у'=>'u',
            'ф'=>'f',
            'х'=>'x',
            'ц'=>'s',
            'ч'=>'ch',
            'ш'=>'sh',
            'щ'=>'sh',
            'ъ'=>'',
            'ы'=>'i',
            'ь'=>'',
            'э'=>'e',
            'ю'=>'yu',
            'я'=>'ya',
        ];

        $text = strtr($text, $map);

        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);

        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    private function buildVariants(string $model): array
    {
        return array_unique([
            $model,
            str_replace('chevrolet ', '', $model),
            str_replace('daewoo ', '', $model),
            str_replace('ravon ', '', $model),
            str_replace(' ', '', $model),
        ]);
    }
}
