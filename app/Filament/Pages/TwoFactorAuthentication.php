<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * TZ 16: admin panelida 2FA (TOTP) sozlash sahifasi — yoqish, QR kodni
 * skanerlab tasdiqlash, recovery kodlarni ko'rish/yangilash, o'chirish.
 * Fortify actionlaridan foydalanadi.
 */
class TwoFactorAuthentication extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Xavfsizlik (2FA)';

    protected static ?string $navigationGroup = 'Sozlamalar';

    public static function getNavigationLabel(): string
    {
        return __('Xavfsizlik (2FA)');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Sozlamalar');
    }

    protected static ?string $title = 'Ikki bosqichli autentifikatsiya';

    public function getTitle(): string
    {
        return __('Ikki bosqichli autentifikatsiya');
    }

    protected static ?string $slug = 'two-factor-authentication';

    protected static string $view = 'filament.pages.two-factor-authentication';

    public ?string $code = null;

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable($this->getAccount());

        Notification::make()
            ->title(__('2FA yoqildi. QR kodni ilovangizda skaner qilib, kodni tasdiqlang.'))
            ->success()
            ->send();
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        try {
            $confirm($this->getAccount(), (string) $this->code);
        } catch (ValidationException $e) {
            Notification::make()
                ->title(__('Kod noto\'g\'ri. Qayta urinib ko\'ring.'))
                ->danger()
                ->send();

            return;
        }

        $this->code = null;

        Notification::make()
            ->title(__('Ikki bosqichli autentifikatsiya tasdiqlandi.'))
            ->success()
            ->send();
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate($this->getAccount());

        Notification::make()
            ->title(__('Yangi recovery kodlar yaratildi.'))
            ->success()
            ->send();
    }

    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        $disable($this->getAccount());
        $this->code = null;

        Notification::make()
            ->title(__('Ikki bosqichli autentifikatsiya o\'chirildi.'))
            ->warning()
            ->send();
    }

    public function getAccount(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
