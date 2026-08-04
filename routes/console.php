<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Jobs\DiscoverOlxBrandsJob;
use App\Jobs\DiscoverOlxModelsJob;
use Illuminate\Support\Facades\Schedule;

// 2026-08-04: model discovery haftalik emas, HAR KUNI ishlaydi — yangi
// model/markalar bir haftagacha kutmasdan, keyingi kunning skanerlashiga
// kirsin. Ketma-ketlik muhim: marka → model → asosiy skanerlash, shuning
// uchun har biri navbatga oldingisidan keyin qo'yiladi (default queue'da
// FIFO tartibda ishlanadi). Model discovery uzoq davom etishi mumkin
// (timeout 3600s) — shuning uchun asosiy skanerlashgacha ~1s45d bufer
// qoldirildi; agar u vaqt ichida ulgurmasa ham, RunParserSourceJob
// mavjud (allaqachon tasdiqlangan) targetlar bilan baribir ishlayveradi,
// yangi topilgan target'lar keyingi kunga qoladi.
Schedule::job(new DiscoverOlxBrandsJob())->dailyAt('21:00');
Schedule::job(new DiscoverOlxModelsJob())->dailyAt('21:15');

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
