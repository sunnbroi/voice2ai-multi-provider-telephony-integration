<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Call;
use Illuminate\Support\Facades\File;

class CleanOldRecordings extends Command
{
    protected $signature = 'recordings:clean-old';
    protected $description = 'Удаляет старые записи звонков, если диск заполнен более чем на 80%';

    public function handle()
    {
        $threshold = 80;
        $minDaysOld = 3;
        $storagePath = storage_path('app/public/recordings');
        $diskUsage = $this->getDiskUsagePercent($storagePath);

        if ($diskUsage < $threshold) {
            $this->info("Диск заполнен на {$diskUsage}%. Всё в порядке.");
            return 0;
        }

        $this->warn("Диск заполнен на {$diskUsage}%. Начинаем удаление старых записей...");

        $cutoffDate = now()->subDays($minDaysOld);

        $calls = Call::whereNotNull('recording_url')
            ->where('call_time', '<', $cutoffDate)
            ->orderBy('call_time')
            ->get();

        $deleted = 0;

        foreach ($calls as $call) {
            $filePath = storage_path('app/public/' . ltrim(str_replace('/storage/', '', $call->recording_url), '/'));

            if (File::exists($filePath)) {
                File::delete($filePath);
                $call->update(['recording_url' => null, 'recording_status' => '']);
                $deleted++;
                $this->info("Удалена запись: {$filePath}");
            }

            if ($this->getDiskUsagePercent($storagePath) < $threshold) {
                break;
            }
        }

        $this->info("Удалено записей: {$deleted}");

        return 0;
    }

    protected function getDiskUsagePercent($path): float
    {
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        return round(100 - ($free / $total * 100), 2);
    }
}
