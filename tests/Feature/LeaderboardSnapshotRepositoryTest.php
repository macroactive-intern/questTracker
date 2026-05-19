<?php

use App\Models\LeaderboardSnapshot;
use App\Repositories\LeaderboardSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('stores daily snapshots and fetches the latest daily history', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $repository = app(LeaderboardSnapshotRepository::class);

    $repository->storeDailySnapshot('arcade', Carbon::today(), [
        ['rank' => 1, 'user_id' => 10, 'score' => 500],
    ]);

    $repository->storeDailySnapshot('arcade', Carbon::today()->subDay(), [
        ['rank' => 1, 'user_id' => 11, 'score' => 400],
    ]);

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'weekly',
        'snapshot_date' => Carbon::today(),
        'data' => [['rank' => 1, 'score' => 900]],
    ]);

    $history = $repository->latestDailySnapshots('arcade', 30);

    expect($history)->toHaveCount(2)
        ->and($history->first()->snapshot_date->toDateString())->toBe('2026-05-20')
        ->and($history->first()->data[0]['score'])->toBe(500);

    Carbon::setTestNow();
});

it('prunes expired daily and weekly snapshots', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'daily',
        'snapshot_date' => now()->subDays(91)->toDateString(),
        'data' => [],
    ]);

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'weekly',
        'snapshot_date' => now()->subDays(731)->toDateString(),
        'data' => [],
    ]);

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'weekly',
        'snapshot_date' => now()->subDays(30)->toDateString(),
        'data' => [],
    ]);

    expect(app(LeaderboardSnapshotRepository::class)->pruneOldSnapshots())->toBe(2)
        ->and(LeaderboardSnapshot::query()->count())->toBe(1);

    Carbon::setTestNow();
});
