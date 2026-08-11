<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class SetLocale
{
    public const SUPPORTED = ['uz', 'ru', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', config('app.locale', 'uz'));

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'uz';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
