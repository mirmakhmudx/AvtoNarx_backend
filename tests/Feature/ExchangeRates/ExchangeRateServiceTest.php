<?php

use App\Models\ExchangeRate;
use App\Services\ExchangeRates\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ExchangeRateService::class);
});

it('returns the amount unchanged when currency is already UZS, without touching the DB', function () {
    expect($this->service->convertToUzs(145000000, 'UZS'))->toBe(145000000);
});

it('returns null when converting a foreign currency with no known rate at all', function () {
    expect($this->service->convertToUzs(100, 'USD'))->toBeNull();
});

it('converts using the rate for the exact given date when present', function () {
    $this->service->setRate('USD', 'UZS', 12700.5, '2026-07-29');

    $result = $this->service->convertToUzs(100, 'USD', '2026-07-29');

    expect($result)->toBe((int) round(100 * 12700.5));
});

it('falls back to the most recent PRIOR rate when no rate exists for the exact date', function () {
    $this->service->setRate('USD', 'UZS', 12000, '2026-07-01');
    $this->service->setRate('USD', 'UZS', 12500, '2026-07-15');

    $result = $this->service->convertToUzs(100, 'USD', '2026-07-20');

    expect($result)->toBe(1250000);
});

it('never uses a FUTURE rate to convert a past-dated amount', function () {
    $this->service->setRate('USD', 'UZS', 13000, '2026-08-15');

    $result = $this->service->convertToUzs(100, 'USD', '2026-07-20');

    expect($result)->toBeNull();
});

it('defaults to today when no date is passed — this is the exact scenario that caught the whereDate bug', function () {
    $this->service->setRate('USD', 'UZS', 12800, now()->toDateString());

    $result = $this->service->convertToUzs(50, 'USD');

    expect($result)->toBe((int) round(50 * 12800));
});

it('setRate upserts — calling it twice for the same day updates rather than duplicating', function () {
    $this->service->setRate('USD', 'UZS', 12000, '2026-07-29');
    $this->service->setRate('USD', 'UZS', 12800, '2026-07-29');

    expect(ExchangeRate::count())->toBe(1);
    expect((float) ExchangeRate::first()->rate)->toBe(12800.0);
});
