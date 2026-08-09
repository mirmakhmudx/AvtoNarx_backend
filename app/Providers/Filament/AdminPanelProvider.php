<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureAdminTwoFactorConfirmed;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('AvtoNarx Admin')
            ->login()
            // Faqat och (oq fon) rejim — qorong'i rejim o'chirilgan, shunda
            // har doim toza, professional oq fon ko'rinadi.
            ->darkMode(false)
            ->colors([
                'primary' => Color::Sky,
            ])
            ->font('Inter')
            ->userMenuItems([
                MenuItem::make()
                    ->label("O'zbekcha")
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => route('admin.locale.set', 'uz')),
                MenuItem::make()
                    ->label('Русский')
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => route('admin.locale.set', 'ru')),
                MenuItem::make()
                    ->label('English')
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => route('admin.locale.set', 'en')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                SetLocale::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAdminTwoFactorConfirmed::class,
            ]);
    }
}
