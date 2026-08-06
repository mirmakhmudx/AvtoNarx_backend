<?php

return array(
    'cache_ttl_seconds' => (int) env('PUBLIC_API_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Kesh do'koni
    |--------------------------------------------------------------------------
    |
    | Ishlab chiqarishda har doim 'redis' (TZ talabi). Testlarda haqiqiy
    | Redis'ga bog'liq bo'lmasligi uchun phpunit.xml PUBLIC_API_CACHE_STORE'ni
    | 'array'ga o'zgartiradi.
    |
    */
    'cache_store' => env('PUBLIC_API_CACHE_STORE', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Yagona pagination usuli (TZ 13-bo'lim)
    |--------------------------------------------------------------------------
    */
    'per_page' => (int) env('PUBLIC_API_PER_PAGE', 20),
    'max_per_page' => (int) env('PUBLIC_API_MAX_PER_PAGE', 100),
);
