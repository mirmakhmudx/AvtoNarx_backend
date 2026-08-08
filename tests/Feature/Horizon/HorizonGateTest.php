<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;

uses(RefreshDatabase::class);

/**
 * TZ 15/16: Horizon dashboard'iga faqat administrator kira oladi. Test Horizon
 * o'rnatilgandan keyin ishlaydi; aks holda o'tkazib yuboriladi (skip).
 */
it('allows only administrators to view the Horizon dashboard (TZ 15)', function () {
    $admin = User::factory()->create(['role' => 'administrator']);
    $editor = User::factory()->create(['role' => 'content_editor']);

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue();
    expect(Gate::forUser($editor)->allows('viewHorizon'))->toBeFalse();
    expect(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
})->skip(
    ! class_exists(Horizon::class),
    'Horizon hali o\'rnatilmagan — avval: composer require laravel/horizon && php artisan horizon:install',
);
