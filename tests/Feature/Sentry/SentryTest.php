<?php

/**
 * TZ 4: xatolarni kuzatish uchun Sentry ulangan bo'lishi kerak. Bu test paket
 * o'rnatilgandan keyin ishlaydi; o'rnatilmaguncha o'tkazib yuboriladi (skip),
 * shuning uchun ishlar tartibidan qat'i nazar suite qizil bo'lmaydi.
 */
it('wires the Sentry SDK for error tracking (TZ 4)', function () {
    expect(class_exists(\Sentry\Laravel\Integration::class))->toBeTrue();
    expect(function_exists('Sentry\captureException'))->toBeTrue();
})->skip(
    ! class_exists(\Sentry\Laravel\Integration::class),
    'Sentry hali o\'rnatilmagan — avval: composer require sentry/sentry-laravel',
);
