<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = array(
        'base_currency',
        'quote_currency',
        'rate',
        'rate_date',
        'source',
    );

    protected $casts = array(
        'rate' => 'decimal:6',
        // Format aniq ko'rsatilgan: 'Y-m-d'. Bu saqlashda ham, o'qishda ham
        // izchil, faqat sana qismi (vaqt komponentisiz) saqlanishini
        // kafolatlaydi. Format ko'rsatilmasa, Laravel ba'zan to'liq
        // vaqt bilan ("2026-07-29 00:00:00") saqlaydi va bu updateOrCreate()
        // qidiruvida mos kelmaslikka olib kelib, unique constraint xatosini
        // keltirib chiqargan edi.
        'rate_date' => 'date:Y-m-d',
    );
}
