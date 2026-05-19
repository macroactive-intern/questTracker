<?php

use App\Models\User;
use App\Repositories\ScoreRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('submits scores and ranks each user by their best score once', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $repository = app(ScoreRepository::class);
    $firstUser = User::factory()->create(['name' => 'First']);
    $secondUser = User::factory()->create(['name' => 'Second']);
    $thirdUser = User::factory()->create(['name' => 'Third']);

    $repository->submitScore([
        'user_id' => $firstUser->id,
        'game_slug' => 'speed-run',
        'score' => 100,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $repository->submitScore([
        'user_id' => $firstUser->id,
        'game_slug' => 'speed-run',
        'score' => 250,
        'source' => 'quest_completion',
        'achieved_at' => now()->subHours(2),
    ]);

    $repository->submitScore([
        'user_id' => $secondUser->id,
        'game_slug' => 'speed-run',
        'score' => 200,
        'source' => 'manual',
        'achieved_at' => now()->subMinutes(30),
    ]);

    $repository->submitScore([
        'user_id' => $thirdUser->id,
        'game_slug' => 'speed-run',
        'score' => 200,
        'source' => 'manual',
        'achieved_at' => now()->subMinutes(15),
    ]);

    $topPlayers = $repository->topPlayers('speed-run');

    expect($topPlayers)->toHaveCount(3)
        ->and($topPlayers->pluck('user_id')->all())->toBe([$firstUser->id, $secondUser->id, $thirdUser->id])
        ->and($topPlayers->pluck('score')->all())->toBe([250, 200, 200])
        ->and($topPlayers->pluck('rank')->all())->toBe([1, 2, 2])
        ->and($topPlayers->first()->achieved_at)->toBe('2026-05-20 10:00:00');

    $rank = $repository->userRank('speed-run', $thirdUser->id);

    expect($rank->score)->toBe(200)
        ->and($rank->rank)->toBe(2);

    Carbon::setTestNow();
});

it('applies daily weekly and alltime leaderboard windows', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $repository = app(ScoreRepository::class);
    $dailyUser = User::factory()->create();
    $weeklyUser = User::factory()->create();
    $oldUser = User::factory()->create();

    foreach ([
        [$dailyUser->id, 300, now()],
        [$weeklyUser->id, 400, now()->subDays(3)],
        [$oldUser->id, 500, now()->subDays(10)],
    ] as [$userId, $score, $achievedAt]) {
        $repository->submitScore([
            'user_id' => $userId,
            'game_slug' => 'maze',
            'score' => $score,
            'source' => 'manual',
            'achieved_at' => $achievedAt,
        ]);
    }

    expect($repository->topPlayers('maze', 'daily')->pluck('user_id')->all())->toBe([$dailyUser->id])
        ->and($repository->topPlayers('maze', 'weekly')->pluck('user_id')->all())->toBe([$weeklyUser->id, $dailyUser->id])
        ->and($repository->topPlayers('maze', 'alltime')->pluck('user_id')->all())->toBe([$oldUser->id, $weeklyUser->id, $dailyUser->id]);

    Carbon::setTestNow();
});

it('lists distinct game slugs', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $repository = app(ScoreRepository::class);
    $user = User::factory()->create();

    foreach (['maze', 'speed-run', 'maze'] as $slug) {
        $repository->submitScore([
            'user_id' => $user->id,
            'game_slug' => $slug,
            'score' => 100,
            'source' => 'manual',
            'achieved_at' => now(),
        ]);
    }

    expect($repository->distinctGameSlugs()->all())->toBe(['maze', 'speed-run']);

    Carbon::setTestNow();
});
