<?php

use App\Http\Middleware\EnsureAdminTwoFactorConfirmed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function runMfaMiddleware(User $user, string $uri = '/admin'): \Symfony\Component\HttpFoundation\Response
{
    $request = Request::create($uri, 'GET');
    $request->setUserResolver(fn () => $user);

    return (new EnsureAdminTwoFactorConfirmed())->handle($request, fn () => response('ok', 200));
}

it('does nothing when MFA is not required (default)', function () {
    config()->set('admin.mfa_required', false);
    $admin = User::factory()->create(array('role' => 'administrator'));
    expect(runMfaMiddleware($admin)->getStatusCode())->toBe(200);
})->skip(! class_exists(\Laravel\Fortify\Fortify::class), 'Fortify o\'rnatilmagan');

it('redirects an administrator without confirmed 2FA when MFA is required (TZ 16)', function () {
    config()->set('admin.mfa_required', true);
    $admin = User::factory()->create(array('role' => 'administrator'));
    expect(runMfaMiddleware($admin, '/admin/dashboard')->getStatusCode())->toBe(302);
})->skip(! class_exists(\Laravel\Fortify\Fortify::class), 'Fortify o\'rnatilmagan');

it('allows an administrator WITH confirmed 2FA when MFA is required', function () {
    config()->set('admin.mfa_required', true);
    $admin = User::factory()->create(array('role' => 'administrator'));
    $admin->two_factor_confirmed_at = now();
    $admin->save();
    expect(runMfaMiddleware($admin, '/admin/dashboard')->getStatusCode())->toBe(200);
})->skip(! class_exists(\Laravel\Fortify\Fortify::class), 'Fortify o\'rnatilmagan');

it('does not enforce 2FA on non-administrators', function () {
    config()->set('admin.mfa_required', true);
    $editor = User::factory()->create(array('role' => 'content_editor'));
    expect(runMfaMiddleware($editor, '/admin/dashboard')->getStatusCode())->toBe(200);
})->skip(! class_exists(\Laravel\Fortify\Fortify::class), 'Fortify o\'rnatilmagan');

it('allows access to the 2FA setup page itself (no redirect loop)', function () {
    config()->set('admin.mfa_required', true);
    config()->set('admin.mfa_setup_path', 'admin/two-factor-authentication');
    $admin = User::factory()->create(array('role' => 'administrator'));
    expect(runMfaMiddleware($admin, '/admin/two-factor-authentication')->getStatusCode())->toBe(200);
})->skip(! class_exists(\Laravel\Fortify\Fortify::class), 'Fortify o\'rnatilmagan');
