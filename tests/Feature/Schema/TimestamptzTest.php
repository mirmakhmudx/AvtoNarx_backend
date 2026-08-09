<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * TZ 7: sana/vaqt ustunlari timestamptz bo'lishi kerak. Bu faqat PostgreSQL'da
 * ma'noli — SQLite'da (test muhiti) timestamptz tushunchasi yo'q, shuning uchun
 * test o'sha yerda o'tkazib yuboriladi. Postgres'li CI'da esa haqiqiy tekshiruv
 * bo'ladi.
 */
it('stores timestamp columns as timestamptz on PostgreSQL (TZ 7)', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('timestamptz faqat PostgreSQL uchun tekshiriladi (test muhiti — sqlite).');
    }

    $checks = [
        ['market_listings', 'created_at'],
        ['market_listings', 'last_seen_at'],
        ['ingestion_batches', 'received_at'],
        ['official_offers', 'created_at'],
        ['audit_logs', 'created_at'],
    ];

    foreach ($checks as [$table, $column]) {
        $result = DB::selectOne(
            'select data_type from information_schema.columns
              where table_schema = current_schema()
                and table_name = ? and column_name = ?',
            [$table, $column],
        );

        expect($result)->not->toBeNull("{$table}.{$column} ustuni topilmadi");
        expect($result->data_type)->toBe(
            'timestamp with time zone',
            "{$table}.{$column} timestamptz bo'lishi kerak edi",
        );
    }
});
