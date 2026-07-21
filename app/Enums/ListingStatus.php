<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Removed = 'removed';
}
