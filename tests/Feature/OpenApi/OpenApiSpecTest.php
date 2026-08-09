<?php

use Symfony\Component\Yaml\Yaml;

it('ships a syntactically valid OpenAPI 3.1 document', function () {
    $path = base_path('docs/openapi.yaml');

    expect(file_exists($path))->toBeTrue();

    $spec = Yaml::parseFile($path);

    expect($spec['openapi'] ?? '')->toStartWith('3.1');
    expect($spec['info']['title'] ?? null)->not->toBeNull();
    expect($spec['info']['version'] ?? null)->not->toBeNull();
    expect($spec['paths'] ?? [])->not->toBeEmpty();
    expect($spec['components']['securitySchemes']['bearerAuth'] ?? null)->not->toBeNull();
});

it('documents every public and ingestion API route (spec does not drift from code)', function () {
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));
    $documented = array_keys($spec['paths']);

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->filter(fn ($uri) => str_starts_with($uri, 'api/v1/'))
        ->reject(fn ($uri) => str_contains($uri, 'admin'))
        ->map(fn ($uri) => '/'.$uri)
        ->unique()
        ->values();

    $missing = $routes->reject(fn ($uri) => in_array($uri, $documented, true))->values()->all();

    if (! empty($missing)) {
        $this->fail('Quyidagi route\'lar OpenAPI hujjatida yo\'q: '.implode(', ', $missing));
    }

    expect($missing)->toBeEmpty();
});

it('does not document phantom api routes that no longer exist in code', function () {
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    $routeUris = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => '/'.$r->uri())
        ->unique()
        ->all();

    $phantom = collect(array_keys($spec['paths']))
        ->filter(fn ($p) => str_starts_with($p, '/api/v1/'))
        ->reject(fn ($p) => in_array($p, $routeUris, true))
        ->values()
        ->all();

    if (! empty($phantom)) {
        $this->fail('OpenAPI hujjatida mavjud, lekin kodda yo\'q route\'lar: '.implode(', ', $phantom));
    }

    expect($phantom)->toBeEmpty();
});
