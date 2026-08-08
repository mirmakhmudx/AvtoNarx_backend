<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

uses(RefreshDatabase::class);

/**
 * TZ 16: admin foydalanuvchilar ikki bosqichli autentifikatsiyani (TOTP)
 * yoqa olishi kerak. Bu Fortify o'rnatilgandan keyin ishlaydi; aks holda skip.
 */
it('lets an admin user enable two-factor authentication (TZ 16)', function () {
    $user = User::factory()->create(['role' => 'administrator']);

    expect($user->two_factor_secret)->toBeNull();

    // Fortify'ning "2FA yoqish" amali — secret va tiklash kodlarini yaratadi.
    app(EnableTwoFactorAuthentication::class)($user);

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_recovery_codes)->not->toBeNull();

    // Tiklash kodlari (recovery codes) ro'yxati bo'sh bo'lmasligi kerak.
    expect($user->recoveryCodes())->not->toBeEmpty();
})->skip(
    ! class_exists(Fortify::class),
    'Fortify hali o\'rnatilmagan — avval: composer require laravel/fortify',
);

it('hides the two-factor secret from array/JSON serialization', function () {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('two_factor_secret');
    expect($array)->not->toHaveKey('two_factor_recovery_codes');
})->skip(
    ! class_exists(Fortify::class),
    'Fortify hali o\'rnatilmagan — avval: composer require laravel/fortify',
);
