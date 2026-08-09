<?php

use App\Services\Parser\Extraction\YearExtractor;

beforeEach(function () {
    $this->extractor = new YearExtractor;
});

it('extracts a valid year from title', function () {
    expect($this->extractor->extract('Chevrolet Cobalt, 2021'))->toBe(2021);
});

it('extracts year even with trim in title', function () {
    expect($this->extractor->extract('Chevrolet Cobalt LT, 2019'))->toBe(2019);
});

it('rejects year below 1950', function () {
    expect($this->extractor->extract('Mashina 1949 yilda ishlab chiqarilgan'))->toBeNull();
});

it('rejects a number that looks like a price, not a year', function () {
    expect($this->extractor->extract('Narxi 2030 sum'))->toBeNull();
});

it('returns null when no year present', function () {
    expect($this->extractor->extract('Chevrolet Cobalt sotiladi'))->toBeNull();
});
