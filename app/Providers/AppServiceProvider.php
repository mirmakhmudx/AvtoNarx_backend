<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        //
    }
    public function boot(): void
    {
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('ingestion', function (Request $request) {
            $tokenId = $request->user()?->currentAccessToken()?->id;

            return Limit::perMinute(30)->by('ingestion:' . ($tokenId ?? $request->ip()));
        });
    }
}
