<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TZ 7-bo'lim: barcha sana/vaqt ustunlari `timestamptz` (vaqt zonasi bilan)
 * bo'lishi shart — Asia/Tashkent (+05) uchun muhim.
 *
 * Ilova UTC'da saqlaydi (config/app.php timezone = UTC), shuning uchun mavjud
 * "naive" (zonasiz) qiymatlarni UTC deb talqin qilib o'tkazamiz — moment
 * o'zgarmaydi, hech qanday 5-soatlik surilish bo'lmaydi.
 *
 * Faqat PostgreSQL'da bajariladi. SQLite (test) da timestamptz tushunchasi
 * yo'q, shuning uchun o'tkazib yuboriladi.
 *
 * Bu migratsiya eng oxirgi bo'lgani uchun `migrate:fresh` da ham yakuniy holat
 * timestamptz bo'ladi. YANGI jadvallar uchun migratsiyalarda `timestampsTz()`
 * ishlatishni unutmang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columnsOfType('timestamp without time zone') as $col) {
            DB::statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE timestamptz USING "%s" AT TIME ZONE \'UTC\'',
                $col->table_name,
                $col->column_name,
                $col->column_name,
            ));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columnsOfType('timestamp with time zone') as $col) {
            DB::statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE timestamp USING "%s" AT TIME ZONE \'UTC\'',
                $col->table_name,
                $col->column_name,
                $col->column_name,
            ));
        }
    }

    /**
     * Joriy sxemadagi berilgan turdagi barcha ustunlar.
     *
     * @return array<int, object>
     */
    private function columnsOfType(string $dataType): array
    {
        return DB::select(
            'select table_name, column_name
               from information_schema.columns
              where table_schema = current_schema()
                and data_type = ?',
            array($dataType),
        );
    }
};
