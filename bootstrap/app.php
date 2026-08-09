<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // TZ 4: Sentry'ga xatolarni yuborish. Paket o'rnatilmagan bo'lsa ham
        // ilova ishlashi uchun mavjudligini tekshiramiz (class_exists).
        if (class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
