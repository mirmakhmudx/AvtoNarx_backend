<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reflects a configured trusted origin on API responses (TZ 16)', function () {
    config()->set('cors.allowed_origins', ['https://trusted.example']);

    $this->withHeaders(['Origin' => 'https://trusted.example'])
        ->getJson('/api/v1/brands')
        ->assertHeader('Access-Control-Allow-Origin', 'https://trusted.example');
});

it('does NOT allow an untrusted origin (TZ 16)', function () {
    config()->set('cors.allowed_origins', ['https://trusted.example']);

    $response = $this->withHeaders(['Origin' => 'https://evil.example'])
        ->getJson('/api/v1/brands');

    // Ishonchsiz origin aks ettirilmasligi kerak.
    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://evil.example');
});

it('handles a CORS preflight (OPTIONS) for a trusted origin', function () {
    config()->set('cors.allowed_origins', ['https://trusted.example']);

    $this->call('OPTIONS', '/api/v1/brands', [], [], [], [
        'HTTP_ORIGIN' => 'https://trusted.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ])->assertHeader('Access-Control-Allow-Origin', 'https://trusted.example');
});
