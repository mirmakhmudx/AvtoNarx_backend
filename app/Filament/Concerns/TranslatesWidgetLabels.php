<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Chart widget'larning static $heading/$description'ini __() orqali tarjima qiladi.
 */
trait TranslatesWidgetLabels
{
    public function getHeading(): string|Htmlable|null
    {
        return filled(static::$heading) ? __(static::$heading) : null;
    }

    public function getDescription(): string|Htmlable|null
    {
        return filled(static::$description) ? __(static::$description) : null;
    }
}
