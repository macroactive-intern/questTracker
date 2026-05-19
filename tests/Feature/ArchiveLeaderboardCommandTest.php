<?php

use App\Models\LeaderboardSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('archives daily top ten leaderboard snapshots for each game slug', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $users = User::factory()->count(11)->create();

    foreach ($users as $index => $user) {
        $user->scores()->create([
            'game_slug' => 'arcade',
            'score' => 1000 - $index,
            'source' => 'manual',
            'achieved_at' => now()->subMinutes($index),
        ]);
    }

    $users->first()->scores()->create([
        'game_slug' => 'maze',
        'score' => 500,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $this->artisan('leaderboard:archive')
        ->expectsOutput('Archiving daily leaderboard snapshots for 2 game(s).')
        ->expectsOutput('Archived arcade leaderboard with 10 entrie(s).')
        ->expectsOutput('Archived maze leaderboard with 1 entrie(s).')
        ->expectsOutput('Deleted 0 old leaderboard snapshot(s).')
        ->expectsOutput('Leaderboard archive complete.')
        ->assertSuccessful();

    $arcade = LeaderboardSnapshot::query()
        ->where('game_slug', 'arcade')
        ->where('period', 'daily')
        ->whereDate('snapshot_date', '2026-05-20')
        ->firstOrFail();

    expect($arcade->data)->toHaveCount(10)
        ->and($arcade->data[0])->toMatchArray([
            'rank' => 1,
            'user_id' => $users->first()->id,
            'score' => 1000,
        ])
        ->and($arcade->data[0]['achieved_at'])->not->toBeNull()
        ->and(LeaderboardSnapshot::query()->where('game_slug', 'maze')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('updates todays snapshot and prunes snapshots older than ninety days', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $user = User::factory()->create();
    $user->scores()->create([
        'game_slug' => 'arcade',
        'score' => 800,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'daily',
        'snapshot_date' => now()->toDateString(),
        'data' => [['rank' => 1, 'score' => 1]],
    ]);

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'daily',
        'snapshot_date' => now()->subDays(91)->toDateString(),
        'data' => [],
    ]);

    $this->artisan('leaderboard:archive')
        ->expectsOutput('Deleted 1 old leaderboard snapshot(s).')
        ->assertSuccessful();

    expect(LeaderboardSnapshot::query()->where('game_slug', 'arcade')->count())->toBe(1);

    $snapshot = LeaderboardSnapshot::query()->firstOrFail();

    expect($snapshot->data[0]['score'])->toBe(800);

    Carbon::setTestNow();
});
