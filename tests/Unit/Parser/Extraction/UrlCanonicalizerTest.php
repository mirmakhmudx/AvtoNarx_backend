<?php

use App\Services\Parser\Extraction\UrlCanonicalizer;

beforeEach(function () {
    $this->canonicalizer = new UrlCanonicalizer();
});

it('strips query parameters', function () {
    $result = $this->canonicalizer->canonicalize(
        'https://www.olx.uz',
        '/d/obyavlenie/test-ID1a2b3c.html?utm_source=test&page=2'
    );

    expect($result)->toBe('https://www.olx.uz/d/obyavlenie/test-ID1a2b3c.html');
});

it('handles absolute urls unchanged in structure', function () {
    $result = $this->canonicalizer->canonicalize(
        'https://www.olx.uz',
        'https://www.olx.uz/d/obyavlenie/test.html'
    );

    expect($result)->toBe('https://www.olx.uz/d/obyavlenie/test.html');
});
