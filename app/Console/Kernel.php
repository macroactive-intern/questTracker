<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        self::scheduleLeaderboardArchive($schedule);
    }

    public static function scheduleLeaderboardArchive(Schedule $schedule): void
    {
        // Laravel's scheduler does not run by itself; this only defines the
        // schedule entry that should be evaluated when schedule:run executes.
        //
        // In production, add a server cron job that calls this every minute:
        // php artisan schedule:run
        $schedule->command('leaderboard:archive')
            ->dailyAt('00:05')
            ->withoutOverlapping();
    }
}
