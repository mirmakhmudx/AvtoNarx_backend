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
        'rate_date' => 'date',
    );
}
