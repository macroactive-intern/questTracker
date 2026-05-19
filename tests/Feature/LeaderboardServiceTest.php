<?php

use App\Models\User;
use App\Repositories\ScoreRepository;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('submits scores and invalidates cached leaderboards for the game', function (): void {
    Cache::flush();
    Carbon::setTestNow('2026-05-20 12:00:00');

    $service = app(LeaderboardService::class);
    $user = User::factory()->create();

    Cache::put('leaderboard.arcade.daily', collect(['stale']), 60);
    Cache::put('leaderboard.arcade.weekly', collect(['stale']), 60);
    Cache::put('leaderboard.arcade.alltime', collect(['stale']), 60);

    $service->submit([
        'user_id' => $user->id,
        'game_slug' => 'arcade',
        'score' => 500,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    expect(Cache::has('leaderboard.arcade.daily'))->toBeFalse()
        ->and(Cache::has('leaderboard.arcade.weekly'))->toBeFalse()
        ->and(Cache::has('leaderboard.arcade.alltime'))->toBeFalse();

    Carbon::setTestNow();
});

it('caches leaderboards behind a stampede-protection lock', function (): void {
    Cache::flush();
    Carbon::setTestNow('2026-05-20 12:00:00');

    $service = app(LeaderboardService::class);
    $user = User::factory()->create(['name' => 'Cache Winner']);

    $service->submit([
        'user_id' => $user->id,
        'game_slug' => 'arcade',
        'score' => 700,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $leaderboard = $service->getLeaderboard('arcade');

    expect($leaderboard)->toHaveCount(1)
        ->and($leaderboard->first()->user_id)->toBe($user->id)
        ->and(Cache::has('leaderboard.arcade.alltime'))->toBeTrue();

    $user->scores()->create([
        'game_slug' => 'arcade',
        'score' => 900,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    expect($service->getLeaderboard('arcade')->first()->score)->toBe(700);

    Carbon::setTestNow();
});

it('returns cached data after briefly waiting when another request owns the lock', function (): void {
    Cache::flush();

    $repository = Mockery::mock(ScoreRepository::class);
    $repository->shouldReceive('topPlayers')->never();

    $service = new LeaderboardService($repository);
    $lock = Cache::lock('leaderboard-building.arcade.alltime', 10);
    $lock->get();

    Cache::put('leaderboard.arcade.alltime', collect([(object) ['score' => 123]]), 60);

    try {
        $leaderboard = $service->getLeaderboard('arcade');

        expect($leaderboard->first()->score)->toBe(123);
    } finally {
        $lock->release();
    }
});

it('falls back to a direct repository read when a locked rebuild is still not cached', function (): void {
    Cache::flush();

    $repository = Mockery::mock(ScoreRepository::class);
    $repository->shouldReceive('topPlayers')
        ->once()
        ->with('arcade', 'alltime', 10)
        ->andReturn(collect([(object) ['score' => 456]]));

    $service = new LeaderboardService($repository);
    $lock = Cache::lock('leaderboard-building.arcade.alltime', 10);
    $lock->get();

    try {
        $leaderboard = $service->getLeaderboard('arcade');

        expect($leaderboard->first()->score)->toBe(456);
    } finally {
        $lock->release();
    }
});

it('gets user ranks through the score repository and can invalidate one period', function (): void {
    Cache::flush();
    Carbon::setTestNow('2026-05-20 12:00:00');

    $service = app(LeaderboardService::class);
    $user = User::factory()->create();

    $service->submit([
        'user_id' => $user->id,
        'game_slug' => 'arcade',
        'score' => 600,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    Cache::put('leaderboard.arcade.daily', collect(['stale']), 60);
    Cache::put('leaderboard.arcade.weekly', collect(['stale']), 60);

    $service->invalidate('arcade', 'daily');

    expect($service->getUserRank('arcade', $user->id)->rank)->toBe(1)
        ->and(Cache::has('leaderboard.arcade.daily'))->toBeFalse()
        ->and(Cache::has('leaderboard.arcade.weekly'))->toBeTrue();

    Carbon::setTestNow();
});
