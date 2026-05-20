<?php

use App\Models\LeaderboardSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows authenticated users to submit manual scores', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/leaderboard/arcade/scores', [
            'score' => 1234,
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.game_slug', 'arcade')
        ->assertJsonPath('data.score', 1234)
        ->assertJsonPath('data.source', 'manual');

    $this->assertDatabaseHas('scores', [
        'user_id' => $user->id,
        'game_slug' => 'arcade',
        'score' => 1234,
        'source' => 'manual',
    ]);

    Carbon::setTestNow();
});

it('requires authentication to submit scores', function (): void {
    $this->postJson('/api/leaderboard/arcade/scores', [
        'score' => 1234,
    ])->assertUnauthorized();
});

it('validates manual scores as non-negative integers within php integer bounds', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/leaderboard/arcade/scores', [
            'score' => 1.9,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('score');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/leaderboard/arcade/scores', [
            'score' => (string) PHP_INT_MAX.'0',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('score');
});

it('does not accept game slugs from manual score request bodies', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/leaderboard/arcade/scores', [
            'game_slug' => 'maze',
            'score' => 777,
        ])
        ->assertCreated()
        ->assertJsonPath('data.game_slug', 'arcade');

    $this->assertDatabaseHas('scores', [
        'user_id' => $user->id,
        'game_slug' => 'arcade',
        'score' => 777,
    ]);
    $this->assertDatabaseMissing('scores', [
        'user_id' => $user->id,
        'game_slug' => 'maze',
        'score' => 777,
    ]);
});

it('returns a public top ten leaderboard through resources', function (): void {
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

    $this->getJson('/api/leaderboard/arcade?period=alltime')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.rank', 1)
        ->assertJsonPath('data.0.user_id', $users->first()->id)
        ->assertJsonPath('data.0.score', 1000)
        ->assertJsonStructure([
            'data' => [
                '*' => ['rank', 'user_id', 'score', 'achieved_at'],
            ],
        ]);

    Carbon::setTestNow();
});

it('returns the authenticated users rank and best score', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $first = User::factory()->create();
    $second = User::factory()->create();

    $first->scores()->create([
        'game_slug' => 'arcade',
        'score' => 500,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $second->scores()->create([
        'game_slug' => 'arcade',
        'score' => 300,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $this->actingAs($second, 'sanctum')
        ->getJson('/api/leaderboard/arcade/me')
        ->assertOk()
        ->assertJsonPath('data.rank', 2)
        ->assertJsonPath('data.best_score', 300);

    Carbon::setTestNow();
});

it('returns the last thirty daily snapshots for a leaderboard', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    for ($daysAgo = 0; $daysAgo < 31; $daysAgo++) {
        LeaderboardSnapshot::query()->create([
            'game_slug' => 'arcade',
            'period' => 'daily',
            'snapshot_date' => now()->subDays($daysAgo)->toDateString(),
            'data' => [['rank' => 1, 'score' => 1000 - $daysAgo]],
        ]);
    }

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'weekly',
        'snapshot_date' => now()->toDateString(),
        'data' => [['rank' => 1, 'score' => 5000]],
    ]);

    $this->getJson('/api/leaderboard/arcade/history')
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('data.0.snapshot_date', '2026-05-20')
        ->assertJsonPath('data.0.data.0.score', 1000);

    Carbon::setTestNow();
});

it('allows tokens with the leaderboard invalidate ability to invalidate leaderboard cache', function (): void {
    Cache::put('leaderboard.arcade.daily', collect(['stale']), 60);
    Cache::put('leaderboard.arcade.weekly', collect(['stale']), 60);

    Sanctum::actingAs(User::factory()->create(), ['leaderboard:invalidate']);

    $this->postJson('/api/leaderboard/arcade/invalidate', [
        'period' => 'daily',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Leaderboard cache invalidated.');

    expect(Cache::has('leaderboard.arcade.daily'))->toBeFalse()
        ->and(Cache::has('leaderboard.arcade.weekly'))->toBeTrue();
});

it('blocks cache invalidation without the leaderboard invalidate ability', function (): void {
    Cache::put('leaderboard.arcade.daily', collect(['stale']), 60);

    Sanctum::actingAs(User::factory()->create(), ['score:submit']);

    $this->postJson('/api/leaderboard/arcade/invalidate', [
        'period' => 'daily',
    ])->assertForbidden();

    expect(Cache::has('leaderboard.arcade.daily'))->toBeTrue();
});

it('blocks cache invalidation for normal issued api tokens', function (): void {
    Cache::put('leaderboard.arcade.daily', collect(['stale']), 60);

    $response = $this->postJson('/api/register', [
        'name' => 'Hadlee',
        'email' => 'hadlee@example.com',
        'password' => 'password123',
        'device_name' => 'pest',
    ]);

    $this->withToken($response->json('token'))
        ->postJson('/api/leaderboard/arcade/invalidate', [
            'period' => 'daily',
        ])
        ->assertForbidden();

    expect(Cache::has('leaderboard.arcade.daily'))->toBeTrue();
});

it('validates periods on leaderboard endpoints', function (): void {
    $this->getJson('/api/leaderboard/arcade?period=monthly')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('period');
});
