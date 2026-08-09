<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes the default liveness endpoint at /up (TZ 20)', function () {
    $this->get('/up')->assertStatus(200);
});

it('reports the process as alive at /health/live without checking dependencies (TZ 20)', function () {
    $this->getJson('/health/live')
        ->assertStatus(200)
        ->assertJson(['status' => 'alive']);
});

it('reports readiness with per-dependency checks at /health/ready (TZ 20)', function () {
    $this->getJson('/health/ready')
        ->assertStatus(200)
        ->assertJson([
            'status' => 'ready',
            'checks' => [
                'database' => 'ok',
                'cache' => 'ok',
            ],
        ]);
});
