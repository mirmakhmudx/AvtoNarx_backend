<?php

return array(
    /*
    |--------------------------------------------------------------------------
    | Narxning global chegaralari (TZ 11-bo'lim, 1-bosqich)
    |--------------------------------------------------------------------------
    |
    | Bozor statistikasiga kiritishdan oldin har bir e'lon narxi shu
    | chegaralar bilan solishtiriladi. Diapazondan tashqarida qolgan
    | narxlar (shu jumladan 0 va "aniq to'liqsiz" — masalan xato bilan
    | kiritilgan juda kichik summalar) statistikaga umuman kirmaydi.
    | Standart qiymatlar so'mda, .env orqali sozlanadi.
    |
    */
    'global_min_price_uzs' => (int) env('MARKET_STATS_GLOBAL_MIN_PRICE_UZS', 3_000_000),
    'global_max_price_uzs' => (int) env('MARKET_STATS_GLOBAL_MAX_PRICE_UZS', 2_000_000_000),

    /*
    |--------------------------------------------------------------------------
    | IQR uchun minimal tanlanma hajmi (TZ 11-bo'lim, 3-4-bosqich)
    |--------------------------------------------------------------------------
    |
    | Global chegaralardan o'tgan tanlanma shu songa yetganidan keyingina
    | IQR (Q1 - 1.5*IQR, Q3 + 1.5*IQR) qo'llaniladi. Kichikroq tanlanmada
    | faqat yuqoridagi global chegaralar ishlatiladi — IQR statistik
    | jihatdan ishonchsiz bo'lib qoladi.
    |
    */
    'iqr_min_sample_size' => (int) env('MARKET_STATS_IQR_MIN_SAMPLE_SIZE', 20),

    /*
    |--------------------------------------------------------------------------
    | Nashr etish uchun minimal tanlanma (TZ 11-bo'lim, "Natija")
    |--------------------------------------------------------------------------
    |
    | 1-9 oralig'ida narx insufficient_sample sababi bilan yashiriladi.
    |
    */
    'min_sample_size' => (int) env('MARKET_STATS_MIN_SAMPLE_SIZE', 10),
);
