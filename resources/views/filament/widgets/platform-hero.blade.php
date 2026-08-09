<x-filament-widgets::widget>
    <div
        class="relative overflow-hidden rounded-2xl p-6 text-white shadow-lg"
        style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 45%, #db2777 100%);"
    >
        {{-- Bezak doiralar --}}
        <div style="position:absolute; top:-60px; right:-40px; width:220px; height:220px; background:rgba(255,255,255,.12); border-radius:9999px;"></div>
        <div style="position:absolute; bottom:-80px; right:120px; width:160px; height:160px; background:rgba(255,255,255,.08); border-radius:9999px;"></div>

        <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl" style="background:rgba(255,255,255,.2);">
                        <x-filament::icon icon="heroicon-o-chart-bar-square" class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight">AvtoNarx</h2>
                        <p class="text-sm text-white/80">O'zbekiston avtomobil narxlari — bozor medianasi ↔ rasmiy salon narxi</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-4">
                @php
                    $items = [
                        ['label' => "Faol e'lonlar", 'value' => $activeListings],
                        ['label' => 'Modellar (statistika)', 'value' => $modelsTracked],
                        ['label' => 'Salon narxlari', 'value' => $officialPrices],
                        ['label' => 'Markalar', 'value' => $brands],
                    ];
                @endphp

                @foreach ($items as $item)
                    <div>
                        <div class="text-3xl font-bold leading-none">{{ number_format($item['value']) }}</div>
                        <div class="mt-1 text-xs font-medium uppercase tracking-wide text-white/70">{{ $item['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
