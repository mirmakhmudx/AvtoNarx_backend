<?php

use App\Jobs\DiscoverOlxBrandsJob;
use App\Jobs\DiscoverOlxModelsJob;
use App\Jobs\ExpireOfficialOffersJob;
use App\Jobs\ExpireStaleListingsJob;
use App\Jobs\FetchExchangeRatesJob;
use App\Jobs\RecalculateStatisticsJob;
use App\Jobs\RunParserSourceJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DiscoverOlxBrandsJob)->dailyAt('21:00');
Schedule::job(new DiscoverOlxModelsJob)->dailyAt('21:15');
Schedule::job(new RunParserSourceJob('olx_uz'))->dailyAt('23:00');
Schedule::job(new ExpireStaleListingsJob)->hourly();
Schedule::job(new ExpireOfficialOffersJob)->hourly();

Schedule::job(new RecalculateStatisticsJob)->hourly()->withoutOverlapping();
Schedule::job(new FetchExchangeRatesJob)->dailyAt('08:00');
