<?php

use App\Services\Parser\Extraction\MoneyExtractor;

beforeEach(function () {
    $this->extractor = new MoneyExtractor;
});

it('extracts UZS amount correctly', function () {
    $result = $this->extractor->extract('145 000 000 сум');

    expect($result)->toBe(['amount' => 145000000, 'currency' => 'UZS']);
});

it('extracts USD amount correctly', function () {
    $result = $this->extractor->extract('11 200 $');

    expect($result)->toBe(['amount' => 11200, 'currency' => 'USD']);
});

it('rejects "dogovornaya" price', function () {
    expect($this->extractor->extract('Договорная'))->toBeNull();
});

it('rejects monthly credit payment price', function () {
    expect($this->extractor->extract('150 000 000 сум/месяц (кредит)'))->toBeNull();
});

it('rejects empty string', function () {
    expect($this->extractor->extract(''))->toBeNull();
});

it('handles non-breaking spaces in numbers', function () {
    $result = $this->extractor->extract("145\xC2\xA0000\xC2\xA0000 сум");

    expect($result)->toBe(['amount' => 145000000, 'currency' => 'UZS']);
});
