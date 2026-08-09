<x-filament-widgets::widget>
    {{-- Oq fonli, yumshoq burchakli karta (pastdagi kartalarga uyg'un) --}}
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        {{-- Sarlavha qatori --}}
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                <x-filament::icon icon="heroicon-o-chart-bar-square" class="h-6 w-6" />
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-950 dark:text-white">AvtoNarx</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __("O'zbekiston avtomobil narxlari — bozor medianasi ↔ rasmiy salon narxi") }}</p>
            </div>
        </div>

        {{-- Metrikalar: flex bilan, yumshoq burchakli ichki kartalar --}}
        @php
            $items = [
                ['label' => __("Faol e'lonlar"), 'value' => $activeListings, 'icon' => 'heroicon-o-truck', 'color' => 'text-primary-600 dark:text-primary-400'],
                ['label' => __('Modellar (statistika)'), 'value' => $modelsTracked, 'icon' => 'heroicon-o-chart-bar', 'color' => 'text-success-600 dark:text-success-400'],
                ['label' => __('Salon narxlari'), 'value' => $officialPrices, 'icon' => 'heroicon-o-shield-check', 'color' => 'text-warning-600 dark:text-warning-400'],
                ['label' => __('Markalar'), 'value' => $brands, 'icon' => 'heroicon-o-tag', 'color' => 'text-info-600 dark:text-info-400'],
            ];
        @endphp

        <div class="mt-6 flex flex-wrap gap-4">
            @foreach ($items as $item)
                <div class="flex-1 rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" style="min-width: 160px;">
                    <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                        <x-filament::icon :icon="$item['icon']" @class(['h-4 w-4', $item['color']]) />
                        <span class="text-xs font-medium">{{ $item['label'] }}</span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($item['value']) }}</div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament::widget>
