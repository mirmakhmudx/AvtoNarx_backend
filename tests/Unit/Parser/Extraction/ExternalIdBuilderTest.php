<?php

use App\Services\Parser\Extraction\ExternalIdBuilder;

beforeEach(function () {
    $this->builder = new ExternalIdBuilder;
});

it('extracts ID from canonical url when present', function () {
    $result = $this->builder->build('https://www.olx.uz/d/obyavlenie/chevrolet-cobalt-2021-ID1a2b3c.html');

    expect($result)->toBe('olx-1a2b3c');
});

it('falls back to path segment when no explicit ID found', function () {
    $result = $this->builder->build('https://www.olx.uz/d/obyavlenie/some-listing-without-id');

    expect($result)->toBe('olx-some-listing-without-id');
});

it('produces the same id for the same url (deterministic)', function () {
    $url = 'https://www.olx.uz/d/obyavlenie/chevrolet-cobalt-2021-ID1a2b3c.html';

    expect($this->builder->build($url))->toBe($this->builder->build($url));
});
