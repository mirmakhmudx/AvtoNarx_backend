<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Jobs\DiscoverOlxBrandsJob;
use App\Jobs\DiscoverOlxModelsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DiscoverOlxBrandsJob())->dailyAt('22:30');
Schedule::job(new DiscoverOlxModelsJob())->weeklyOn(1, '23:00'); // har dushanba

use App\Jobs\RecalculateStatisticsJob;
use App\Jobs\RunParserSourceJob;

Schedule::job(new RunParserSourceJob('olx_uz'))->dailyAt('23:00');

use App\Jobs\ExpireOfficialOffersJob;
use App\Jobs\ExpireStaleListingsJob;

// TZ bo'lim 12 (Lifecycle): 72 soat kuzatuvsiz qolgan e'lonlarni inactive
// qilish — statistika qayta hisoblanishidan OLDIN ishlashi kerak, aks holda
// eskirgan e'lonlar bir soatga tanlanmaga kirib qolishi mumkin.
Schedule::job(new ExpireStaleListingsJob())->hourly();
Schedule::job(new ExpireOfficialOffersJob())->hourly();

Schedule::job(new RecalculateStatisticsJob())->dailyAt('23:45');
