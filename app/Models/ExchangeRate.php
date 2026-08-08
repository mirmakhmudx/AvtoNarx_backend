<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'rate_date',
        'source',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'rate_date' => 'date:Y-m-d',
    ];
}
