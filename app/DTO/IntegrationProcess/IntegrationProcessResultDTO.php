<?php

namespace App\DTO\IntegrationProcess;

use App\DTO\BaseDTO;

class IntegrationProcessResultDTO extends BaseDTO
{
    public int $integrationId;
    public string $from;
    public string $to;
    public int $callsCount;
}
