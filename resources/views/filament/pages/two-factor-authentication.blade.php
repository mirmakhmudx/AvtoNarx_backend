<x-filament-panels::page>
    @php
        $user = $this->getAccount();
        $started = ! is_null($user->two_factor_secret);
        $confirmed = ! is_null($user->two_factor_confirmed_at);
    @endphp

    <div class="max-w-2xl space-y-6">

        @if (! $started)
            <x-filament::section>
                <x-slot name="heading">{{ __("Ikki bosqichli autentifikatsiya o'chiq") }}</x-slot>
                <p class="text-sm text-gray-600">
                    {{ __("Hisobingizni parolga qo'shimcha ravishda telefoningizdagi autentifikator ilovasi (Google Authenticator, 1Password va h.k.) bilan himoyalang.") }}
                </p>
                <div class="mt-4">
                    <x-filament::button wire:click="enable" icon="heroicon-o-shield-check">
                        {{ __("2FA'ni yoqish") }}
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif (! $confirmed)
            <x-filament::section>
                <x-slot name="heading">{{ __("QR kodni skaner qiling") }}</x-slot>
                <p class="text-sm text-gray-600">
                    {{ __("Quyidagi QR kodni autentifikator ilovangizda skaner qiling, so'ng ilovadagi 6 xonali kodni kiriting.") }}
                </p>

                <div class="mt-4 inline-block rounded-lg bg-white p-4 ring-1 ring-gray-200">
                    {!! $user->twoFactorQrCodeSvg() !!}
                </div>

                <div class="mt-4 max-w-xs">
                    <input
                        type="text"
                        wire:model="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="123456"
                        class="fi-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>

                <div class="mt-4 flex gap-3">
                    <x-filament::button wire:click="confirm">{{ __("Tasdiqlash") }}</x-filament::button>
                    <x-filament::button color="gray" wire:click="disable">{{ __("Bekor qilish") }}</x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __("Recovery (tiklash) kodlari") }}</x-slot>
                <p class="text-sm text-gray-600">
                    {{ __("Telefoningizni yo'qotsangiz, quyidagi kodlar bilan kira olasiz. Ularni xavfsiz joyda saqlang.") }}
                </p>
                <div class="mt-4 grid grid-cols-2 gap-2 font-mono text-sm">
                    @foreach ($user->recoveryCodes() as $recoveryCode)
                        <div class="rounded bg-gray-50 px-3 py-1 ring-1 ring-gray-200">{{ $recoveryCode }}</div>
                    @endforeach
                </div>
            </x-filament::section>

        @else
            <x-filament::section>
                <x-slot name="heading">
                    <span class="text-success-600">✓ {{ __("Ikki bosqichli autentifikatsiya yoqilgan") }}</span>
                </x-slot>
                <p class="text-sm text-gray-600">{{ __("Hisobingiz 2FA bilan himoyalangan.") }}</p>
                <div class="mt-4 flex gap-3">
                    <x-filament::button color="gray" wire:click="regenerateRecoveryCodes">
                        {{ __("Recovery kodlarni yangilash") }}
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="disable">
                        {{ __("2FA'ni o'chirish") }}
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __("Recovery (tiklash) kodlari") }}</x-slot>
                <div class="mt-2 grid grid-cols-2 gap-2 font-mono text-sm">
                    @foreach ($user->recoveryCodes() as $recoveryCode)
                        <div class="rounded bg-gray-50 px-3 py-1 ring-1 ring-gray-200">{{ $recoveryCode }}</div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
