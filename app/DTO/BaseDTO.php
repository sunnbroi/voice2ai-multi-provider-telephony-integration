<?php

namespace App\DTO;

abstract class BaseDTO
{
    public function __construct()
    {
    }

    /**
     * Считаать значения из json array
     * @param array $data
     * @return void
     */
    public function parseFromArray(array $data): void
    {
    }
}
