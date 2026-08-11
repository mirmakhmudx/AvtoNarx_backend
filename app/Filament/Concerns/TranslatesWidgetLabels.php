<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;


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
