<?php

use App\Enums\LeaderboardPeriod;

it('keeps leaderboard period values centralized', function (): void {
    expect(LeaderboardPeriod::values())->toBe([
        'daily',
        'weekly',
        'alltime',
    ]);
});
