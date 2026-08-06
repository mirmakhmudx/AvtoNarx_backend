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
// TZ 11-bo'lim: agregatlar "kamida soatiga bir marta" qayta hisoblanishi shart
// (TZ 17: "agregatlar 3 soatdan eski" — alert sharti). Shu sabab dailyAt emas,
// hourly. Bir vaqtda ikkita recalc ishlamasligi uchun jobning o'zida
// WithoutOverlapping middleware bor.
Schedule::job(new RecalculateStatisticsJob())->hourly()->withoutOverlapping();
Schedule::job(new FetchExchangeRatesJob())->dailyAt('08:00');
