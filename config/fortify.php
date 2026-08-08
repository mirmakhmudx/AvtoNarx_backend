<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Fortify (TZ 16) — faqat ikki bosqichli autentifikatsiya (2FA) uchun
|--------------------------------------------------------------------------
| Filament o'z login sahifasiga ega, shuning uchun Fortify'ning view'lari
| o'chirilgan (views=false) va faqat twoFactorAuthentication feature yoqilgan.
| Bu Filament bilan yonma-yon, to'qnashuvsiz ishlaydi.
*/

return [

    'guard' => 'web',

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'home' => '/admin',

    'prefix' => '',

    'domain' => null,

    'lowercase_usernames' => true,

    'views' => false,

    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];
