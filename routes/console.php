<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\DiscoverOlxBrandsJob;
use App\Jobs\DiscoverOlxModelsJob;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\RecalculateStatisticsJob;
use App\Jobs\RunParserSourceJob;
use App\Jobs\ExpireOfficialOffersJob;
use App\Jobs\ExpireStaleListingsJob;
use App\Jobs\FetchExchangeRatesJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DiscoverOlxBrandsJob())->dailyAt('21:00');
Schedule::job(new DiscoverOlxModelsJob())->dailyAt('21:15');
Schedule::job(new RunParserSourceJob('olx_uz'))->dailyAt('23:00');
Schedule::job(new ExpireStaleListingsJob())->hourly();
Schedule::job(new ExpireOfficialOffersJob())->hourly();
Schedule::job(new RecalculateStatisticsJob())->dailyAt('23:45');
Schedule::job(new FetchExchangeRatesJob())->dailyAt('08:00');
