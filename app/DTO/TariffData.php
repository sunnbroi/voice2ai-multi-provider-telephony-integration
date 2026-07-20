<?php

namespace App\DTO;

class TariffData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $currency,
        public readonly float $priceOfMinute,
    ) {
        parent::__construct();
    }
}
