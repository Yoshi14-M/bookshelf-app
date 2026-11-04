<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * 読書計画のバッチは毎日 `READING_PLAN_BATCH_TIME`（既定 20:00）に、先に失効、次にリマインドの順で実行する
     * （同時刻のイベントは定義した順に実行される）。
     */
    protected function schedule(Schedule $schedule): void
    {
        $time = config('bookshelf.reading_plan_batch_time');

        $schedule->command('reading-plans:expire')->dailyAt($time);
        $schedule->command('reading-plans:remind')->dailyAt($time);
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
