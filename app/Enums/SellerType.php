<?php

namespace App\Enums;

enum SellerType: string
{
    case Private = 'private';
    case Dealer = 'dealer';
    case Unknown = 'unknown';
}
