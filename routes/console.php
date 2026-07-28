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
Schedule::job(new RecalculateStatisticsJob())->dailyAt('23:45');
