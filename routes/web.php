<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(array(
        'name' => 'AvtoNarx Backend API',
        'status' => 'ok',
    ));
});

// TZ 20: sog'liq endpointlari (/up bootstrap/app.php'da ro'yxatdan o'tgan).
Route::get('/health/live', array(HealthController::class, 'live'));
Route::get('/health/ready', array(HealthController::class, 'ready'));
