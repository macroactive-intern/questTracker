<?php

use App\Models\LeaderboardSnapshot;
use App\Services\LeaderboardSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('stores retrieves and prunes snapshots through the snapshot service boundary', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $service = app(LeaderboardSnapshotService::class);

    $service->storeDailySnapshot('arcade', Carbon::today(), [
        ['rank' => 1, 'user_id' => 10, 'score' => 500],
    ]);

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'daily',
        'snapshot_date' => now()->subDays(91)->toDateString(),
        'data' => [],
    ]);

    expect($service->latestDailySnapshotData('arcade')->first())->toMatchArray([
        'snapshot_date' => '2026-05-20',
        'data' => [
            ['rank' => 1, 'user_id' => 10, 'score' => 500],
        ],
    ])
        ->and($service->pruneOldSnapshots())->toBe(1)
        ->and(LeaderboardSnapshot::query()->count())->toBe(1);

    Carbon::setTestNow();
});
