<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('leaves:process-delegate-timeouts')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        $schedule->command('edoc:purge-retention')
            ->dailyAt('02:30')
            ->withoutOverlapping();

        if (config('edoc.queue.run_scheduled_worker')) {
            $schedule->command(
                'queue:work database --queue=ocr,documents,notifications,default --stop-when-empty --tries=3 --timeout=720 --max-time=780'
            )->everyMinute()->withoutOverlapping(2);
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
