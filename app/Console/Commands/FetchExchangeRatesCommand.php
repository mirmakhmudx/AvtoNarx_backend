<?php

namespace App\Console\Commands;

use App\Services\ExchangeRates\CbuExchangeRateFetcher;
use Illuminate\Console\Command;

class FetchExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rates:fetch';

    protected $description = 'cbu.uz saytidan USD/EUR kurslarini olib, bazaga yozadi';

    public function handle(CbuExchangeRateFetcher $fetcher): int
    {
        $this->info('cbu.uz\'dan kurslar so\'ralmoqda...');

        try {
            $updated = $fetcher->fetchAndStore();
        } catch (\Throwable $e) {
            $this->error('Xato: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($updated as $currency => $rate) {
            $this->line("  {$currency}/UZS = ".number_format($rate, 2));
        }

        $this->info('Muvaffaqiyatli yangilandi.');

        return self::SUCCESS;
    }
}
