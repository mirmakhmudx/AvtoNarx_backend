<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * TZ 20-bo'lim: sog'liq (health) endpointlari.
 *  - GET /up            — Laravel'ning standart liveness'i (bootstrap/app.php).
 *  - GET /health/live   — jarayon tirikmi (hech qanday tashqi bog'liqlikni tekshirmaydi).
 *  - GET /health/ready  — trafik qabul qilishga tayyormi (DB + cache/Redis tekshiriladi).
 *
 * Liveness va readiness'ni ajratish orkestrator (k8s/docker) uchun muhim:
 * readiness FAIL bo'lsa pod trafikdan chiqariladi, lekin liveness OK bo'lsa
 * qayta ishga tushirilmaydi (bog'liqlik tiklanishini kutadi).
 */
class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'alive'], 200);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $ok = ! in_array('fail', $checks, true);

        return response()->json([
            'status' => $ok ? 'ready' : 'not_ready',
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::selectOne('select 1 as ok');

            return 'ok';
        } catch (Throwable $e) {
            return 'fail';
        }
    }

    private function checkCache(): string
    {
        try {
            $key = 'health:ready:probe';
            Cache::put($key, '1', 5);

            return Cache::get($key) === '1' ? 'ok' : 'fail';
        } catch (Throwable $e) {
            return 'fail';
        }
    }
}
