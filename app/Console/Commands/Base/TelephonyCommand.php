<?php

namespace App\Console\Commands\Base;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OpenAI\Factory;

abstract class TelephonyCommand extends Command
{
    /**
     * @param $string
     * @param $verbosity
     * @return void
     */
    public function info($string, $verbosity = null)
    {
        Log::info($string);
        parent::info($string, $verbosity);
    }
}
