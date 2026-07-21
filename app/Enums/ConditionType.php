<?php

namespace App\Enums;

enum ConditionType: string
{
    case New = 'new';
    case Used = 'used';
    case Unknown = 'unknown';
}
