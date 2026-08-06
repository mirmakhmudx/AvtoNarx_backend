<?php

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('throttles public API requests at 120 per minute per IP (TZ 13-bo\'lim)', function () {
    Brand::create(array('name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1));

    for ($i = 0; $i < 120; $i++) {
        $response = $this->getJson('/api/v1/brands');
        $response->assertOk();
    }

    $response = $this->getJson('/api/v1/brands');
    $response->assertStatus(429);
});
