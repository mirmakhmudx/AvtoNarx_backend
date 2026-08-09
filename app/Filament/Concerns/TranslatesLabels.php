<?php

namespace App\Filament\Concerns;

/**
 * Resource'larning static navigatsiya/model nomlarini __() orqali tarjima qiladi.
 */
trait TranslatesLabels
{
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }

    public static function getNavigationGroup(): ?string
    {
        $group = parent::getNavigationGroup();

        return $group !== null ? __($group) : null;
    }
}
