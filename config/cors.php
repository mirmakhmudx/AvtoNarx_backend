<?php

/*
|--------------------------------------------------------------------------
| CORS sozlamalari (TZ 16: "CORS faqat ishonchli frontend uchun")
|--------------------------------------------------------------------------
|
| Standart Laravel barcha domenlarga ochiq ('*'). Bu yerda esa ruxsat etilgan
| origin'lar .env orqali aniq beriladi — CORS_ALLOWED_ORIGINS (vergul bilan
| ajratilgan). Hech narsa berilmasa, ro'yxat BO'SH bo'ladi, ya'ni cross-origin
| so'rovlarga ruxsat berilmaydi (eng xavfsiz standart).
|
| Prod misol:
|   CORS_ALLOWED_ORIGINS=https://avtonarx.uz,https://www.avtonarx.uz
|
*/

return array(

    'paths' => array('api/*'),

    'allowed_methods' => array('GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'),

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    // Subdomen shablonlari kerak bo'lsa (masalan har qanday *.avtonarx.uz),
    // CORS_ALLOWED_ORIGIN_PATTERNS orqali regexp berish mumkin.
    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')),
    ))),

    'allowed_headers' => array('*'),

    // Public API ETag/Last-Modified qaytaradi — brauzer ularni o'qiy olishi uchun ochamiz.
    'exposed_headers' => array('ETag', 'Last-Modified'),

    'max_age' => (int) env('CORS_MAX_AGE', 3600),

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

);
