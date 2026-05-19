<?php

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;

it('schedules leaderboard archival without overlapping runs', function (): void {
    $schedule = app(Schedule::class);

    Kernel::scheduleLeaderboardArchive($schedule);

    $event = collect($schedule->events())
        ->first(fn ($event): bool => str_contains($event->command ?? '', 'leaderboard:archive'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('5 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
