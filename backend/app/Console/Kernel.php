<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\ProcessWhatsappNotificationsJob;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new ProcessWhatsappNotificationsJob())->everyFiveMinutes();
    }

    protected function commands(): array
    {
        return [
            // custom commands
        ];
    }
}
