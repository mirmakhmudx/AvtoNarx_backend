<?php

namespace App\Enums;

enum NormalizationStatus: string
{
    case Matched = 'matched';
    case Pending = 'pending';
    case Rejected = 'rejected';
}
