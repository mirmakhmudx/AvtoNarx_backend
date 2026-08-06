<?php
 
use App\Models\ParserClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
 
uses(RefreshDatabase::class);
 
/**
 * TZ 13-bo'lim: public rate limit — 120 so'rov/daqiqa/IP.
 * 120 tagacha so'rov o'tadi, 121-so'rov 429 (Too Many Requests) qaytaradi.
 */
it('throttles the public API at 120 requests per minute per IP (TZ 13)', function () {
    for ($i = 0; $i < 120; $i++) {
        $this->getJson('/api/v1/brands')->assertOk();
    }
 
    $this->getJson('/api/v1/brands')->assertStatus(429);
});
 
/**
 * TZ 13-bo'lim: ingestion rate limit — 30 so'rov/daqiqa/token.
 * Heartbeat endpoint'i ingestion guruhida, shuning uchun u ham shu limit ostida.
 * 30 tagacha o'tadi, 31-so'rov 429 qaytaradi.
 */
it('throttles the ingestion API at 30 requests per minute per token (TZ 13)', function () {
    $client = ParserClient::create(array(
        'name' => 'Rate limit test parser',
        'is_active' => true,
        'allowed_source_ids' => array(),
    ));
 
    Sanctum::actingAs($client, ['*']);
 
    for ($i = 0; $i < 30; $i++) {
        $this->postJson('/api/v1/ingestion/heartbeat')->assertOk();
    }
 
    $this->postJson('/api/v1/ingestion/heartbeat')->assertStatus(429);
});
 
/**
 * Public va ingestion limitlari BIR-BIRIDAN mustaqil bo'lishi kerak — public
 * limitni tugatish ingestion'ga ta'sir qilmasligi lozim (alohida kalitlar).
 */
it('keeps public and ingestion limits independent', function () {
    // Public limitni to'ldiramiz.
    for ($i = 0; $i < 120; $i++) {
        $this->getJson('/api/v1/brands');
    }
    $this->getJson('/api/v1/brands')->assertStatus(429);
 
    // Ingestion baribir ishlashi kerak.
    $client = ParserClient::create(array(
        'name' => 'Independent limit parser',
        'is_active' => true,
        'allowed_source_ids' => array(),
    ));
    Sanctum::actingAs($client, ['*']);
 
    $this->postJson('/api/v1/ingestion/heartbeat')->assertOk();
});
