<?php

use App\Services\Parser\Extraction\TitleModelMatcher;

beforeEach(function () {
    $this->matcher = new TitleModelMatcher();
});

it('accepts genuine matches including cyrillic and misspellings', function (string $title, string $model) {
    expect($this->matcher->matches($title, $model))->toBeTrue();
})->with([
    ['УАЗ Патриот 2019', 'Patriot'],
    ['Chevrolet Джентра 2015 holati aʼlo', 'Gentra'],
    ['Cobalt 2020 avtomat', 'Cobalt'],
    ['Kobalt 2020 srochno', 'Cobalt'],
    ['Nexia 3 2018 yil', 'Nexia 3'],
    ['Спарк 2015', 'Spark'],
    ['Матиз сотилади', 'Matiz'],
    ['Ласетти 2013 yaxshi holatda', 'Lacetti'],
]);

it('rejects genuine mismatches (the Simbir noise problem)', function (string $title, string $model) {
    expect($this->matcher->matches($title, $model))->toBeFalse();
})->with([
    ["Luaz sro'chna sotiladi", 'Simbir'],
    ['Продаю машину под проект', 'Simbir'],
    ['Увз патирёд ишлабчикорилган йили2008 кам юрган', 'Simbir'],
    ['Malibu 2 2021', 'Simbir'],
    ['Chevrolet Spark 2016', 'Cobalt'],
    ['Kia Sportage 2020', 'Tucson'],
]);

it('returns true when title or model is empty (cannot decide)', function () {
    expect($this->matcher->matches('', 'Cobalt'))->toBeTrue();
    expect($this->matcher->matches('Cobalt 2020', ''))->toBeTrue();
});

it('requires exact match for very short model codes (fuzzy disabled)', function () {
    expect($this->matcher->matches('BMW X6 2020', 'X5'))->toBeFalse();
    expect($this->matcher->matches('BMW X5 2020', 'X5'))->toBeTrue();
});
