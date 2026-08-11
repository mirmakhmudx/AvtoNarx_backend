<?php

namespace App\Services\ExchangeRates;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CbuExchangeRateFetcher
{
    private const API_URL = 'https://cbu.uz/ru/arkhiv-kursov-valyut/json/';

    private const SOURCE_LABEL = 'cbu.uz';

    private const TRACKED_CURRENCIES = ['USD', 'EUR'];

    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
    ) {}


    public function fetchAndStore(): array
    {
        $response = Http::timeout(15)->get(self::API_URL);

        if (! $response->successful()) {
            throw new \RuntimeException('cbu.uz API javob bermadi (HTTP '.$response->status().').');
        }

        $rows = $response->json();

        if (! is_array($rows)) {
            throw new \RuntimeException('cbu.uz API kutilmagan formatda javob qaytardi.');
        }

        $updated = [];

        foreach ($rows as $row) {
            $code = $row['Ccy'] ?? null;

            if ($code === null || ! in_array($code, self::TRACKED_CURRENCIES, true)) {
                continue;
            }

            $nominal = (float) ($row['Nominal'] ?? 1);
            $rawRate = (float) ($row['Rate'] ?? 0);

            if ($nominal <= 0 || $rawRate <= 0) {
                Log::warning("CbuExchangeRateFetcher: {$code} uchun yaroqsiz qiymat — Nominal={$nominal}, Rate={$rawRate}, o'tkazib yuborildi.");

                continue;
            }

            // Nominal ba'zi valyutalar uchun 1 dan katta bo'lishi mumkin
            // (masalan "10 IDR uchun X so'm"), shuning uchun har doim 1 birlik
            // narxiga normallashtiramiz. USD/EUR uchun bu odatda 1 ga teng.
            $ratePerUnit = $rawRate / $nominal;

            $rateDate = isset($row['Date'])
                ? Carbon::createFromFormat('d.m.Y', $row['Date'])->toDateString()
                : Carbon::today()->toDateString();

            $this->exchangeRateService->setRate(
                $code,
                'UZS',
                $ratePerUnit,
                $rateDate,
                self::SOURCE_LABEL,
            );

            $updated[$code] = $ratePerUnit;
        }

        $missing = array_diff(self::TRACKED_CURRENCIES, array_keys($updated));

        if (! empty($missing)) {
            Log::warning('CbuExchangeRateFetcher: kutilgan valyutalar javobda topilmadi — '.implode(', ', $missing));
        }

        if (empty($updated)) {
            throw new \RuntimeException('cbu.uz javobida USD/EUR uchun hech qanday kurs topilmadi.');
        }

        return $updated;
    }
}
