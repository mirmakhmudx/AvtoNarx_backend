<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'AvtoNarx Backend API',
        'status' => 'ok',
    ]);
});

// TZ 20: sog'liq endpointlari (/up bootstrap/app.php'da ro'yxatdan o'tgan).
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
