<?php

use App\Services\Parser\Extraction\TitleModelMatcher;

beforeEach(function () {
    $this->matcher = new TitleModelMatcher();
});

it('matches when title contains the expected model name', function () {
    expect($this->matcher->matches('Daewoo Tacuma, 2005', 'Tacuma'))->toBeTrue();
});

it('matches regardless of case, dashes and punctuation', function () {
    expect($this->matcher->matches('DAEWOO-TACUMA, otlichnoe sostoyanie!', 'Tacuma'))->toBeTrue();
});

it('rejects the exact bug from the screenshot: wrong model shown on target page', function () {
    // "Daewoo Tacuma" target sahifasida "Daewoo Matiz" e'loni chiqqan holat —
    // reason=extended_search belgisisiz, faqat sarlavha orqali aniqlanadigan bag.
    expect($this->matcher->matches('Daewoo Matiz, 2010', 'Tacuma'))->toBeFalse();
});

it('rejects when title is a completely unrelated item', function () {
    expect($this->matcher->matches('Chasy naruchnye Casio', 'Tacuma'))->toBeFalse();
});

it('treats empty title as a match (handled by other rules, not this one)', function () {
    expect($this->matcher->matches('', 'Tacuma'))->toBeTrue();
});

it('treats empty expected model as a match (nothing to compare against)', function () {
    expect($this->matcher->matches('Daewoo Matiz, 2010', ''))->toBeTrue();
});

it('matches multi-word model names regardless of spacing', function () {
    expect($this->matcher->matches('Chevrolet Cobalt LT, 2021', 'Cobalt'))->toBeTrue();
});
