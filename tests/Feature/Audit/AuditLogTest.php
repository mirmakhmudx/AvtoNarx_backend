<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records created/updated/deleted audit logs for an admin action (TZ 14)', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    // CREATED
    $brand = Brand::create(array('name' => 'Chevrolet', 'slug' => 'chevrolet', 'is_active' => true, 'sort_order' => 1));

    $createdLog = AuditLog::where('auditable_type', Brand::class)
        ->where('auditable_id', $brand->id)
        ->where('action', 'created')
        ->first();

    expect($createdLog)->not->toBeNull();
    expect($createdLog->user_id)->toBe($admin->id);
    expect($createdLog->ip_address)->not->toBeNull();
    expect($createdLog->new_values['name'])->toBe('Chevrolet');

    // UPDATED — eski va yangi qiymat yoziladi
    $brand->update(array('name' => 'Chevrolet Renamed'));

    $updatedLog = AuditLog::where('action', 'updated')->latest('id')->first();

    expect($updatedLog)->not->toBeNull();
    expect($updatedLog->old_values['name'])->toBe('Chevrolet');
    expect($updatedLog->new_values['name'])->toBe('Chevrolet Renamed');

    // DELETED
    $brand->delete();

    $deletedLog = AuditLog::where('action', 'deleted')->latest('id')->first();

    expect($deletedLog)->not->toBeNull();
    expect($deletedLog->old_values['name'])->toBe('Chevrolet Renamed');
});

it('does NOT record audit logs when there is no authenticated admin (e.g. ingestion/CLI)', function () {
    // actingAs YO'Q — ya'ni admin yo'q (parser/ingestion yozuvlari kabi).
    Brand::create(array('name' => 'Ravon', 'slug' => 'ravon', 'is_active' => true, 'sort_order' => 2));

    expect(AuditLog::count())->toBe(0);
});

it('only stores changed fields (not the whole row) on update', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $brand = Brand::create(array('name' => 'BYD', 'slug' => 'byd', 'is_active' => true, 'sort_order' => 3));

    $brand->update(array('sort_order' => 99));

    $updatedLog = AuditLog::where('action', 'updated')->latest('id')->first();

    expect(array_keys($updatedLog->new_values))->toContain('sort_order');
    expect(array_keys($updatedLog->new_values))->not->toContain('name');
    expect($updatedLog->old_values['sort_order'])->toEqual(3);
    expect($updatedLog->new_values['sort_order'])->toEqual(99);
});
