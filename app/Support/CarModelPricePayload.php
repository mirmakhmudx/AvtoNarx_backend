<?php

namespace App\Support;

use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;

final class CarModelPricePayload
{
    public function __construct(
        public readonly ?OfficialOffer $officialOffer,
        public readonly ?MarketPriceStatistic $marketStatistic,
    ) {
    }
}
