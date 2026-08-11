<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;


class EnsureAdminTwoFactorConfirmed
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('admin.mfa_required', false)) {
            return $next($request);
        }

        $user = $request->user();

        // Faqat administratorlar uchun majburiy (TZ 16). Boshqalarni tegmaymiz.
        if (! $user instanceof User || ! $user->isAdministrator()) {
            return $next($request);
        }

        // Allaqachon 2FA tasdiqlangan bo'lsa — ruxsat.
        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        $setupPath = (string) config('admin.mfa_setup_path', 'admin/two-factor-authentication');

        // Sozlash sahifasi va chiqish (logout) istisno — qulflanmaslik uchun.
        if ($request->is($setupPath) || $request->is('admin/logout') || $request->routeIs('filament.admin.auth.logout')) {
            return $next($request);
        }

        return redirect('/'.ltrim($setupPath, '/'));
    }
}
