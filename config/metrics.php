<?php

/*
|--------------------------------------------------------------------------
| Metrikalar (§17) — Prometheus /metrics endpoint
|--------------------------------------------------------------------------
| token: bo'sh bo'lsa — endpoint ochiq (faqat ichki tarmoq uchun). To'ldirilsa,
|        scraper "Authorization: Bearer <token>" yuborishi shart.
| stale_source_hours: manba shu soatdan beri batch yubormasa "eskirgan" hisoblanadi.
*/

return [
    'enabled' => (bool) env('METRICS_ENABLED', true),
    'token' => env('METRICS_TOKEN', ''),
    'stale_source_hours' => (int) env('METRICS_STALE_SOURCE_HOURS', 24),
];
