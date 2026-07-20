<?php

namespace App\Jobs;

use App\Console\Commands\FetchBinotelCalls;
use App\Models\Call;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Внутренний класс Job для обработки звонка в очереди
 */
class ProcessCallSummaryJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $call;

    public function __construct(Call $call)
    {
        $this->call = $call;
    }

    public function handle()
    {
        // Создаём экземпляр команды и вызываем метод обработки звонка
        app(FetchBinotelCalls::class)->processCallSummary($this->call);
    }
}
