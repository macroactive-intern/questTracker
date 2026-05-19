<?php

use App\Models\LeaderboardSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Carbon::setTestNow('2026-05-20 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('accepts authenticated manual score submissions', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/scores', [
            'game_slug' => 'arcade',
            'score' => 1250,
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.game_slug', 'arcade')
        ->assertJsonPath('data.score', 1250)
        ->assertJsonPath('data.source', 'manual');

    $this->assertDatabaseHas('scores', [
        'user_id' => $user->id,
        'game_slug' => 'arcade',
        'score' => 1250,
        'source' => 'manual',
    ]);
});

it('returns top ten scores ordered descending with one row per player', function (): void {
    $users = User::factory()->count(12)->create();

    foreach ($users as $index => $user) {
        $user->scores()->create([
            'game_slug' => 'arcade',
            'score' => 1000 - $index,
            'source' => 'manual',
            'achieved_at' => now()->subMinutes($index),
        ]);
    }

    $users[0]->scores()->create([
        'game_slug' => 'arcade',
        'score' => 50,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $this->getJson('/api/leaderboard/arcade')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.user_id', $users[0]->id)
        ->assertJsonPath('data.0.score', 1000)
        ->assertJsonPath('data.9.user_id', $users[9]->id)
        ->assertJsonPath('data.9.score', 991);
});

it('applies daily and weekly leaderboard filters', function (): void {
    [$dailyUser, $weeklyUser, $oldUser] = User::factory()->count(3)->create();

    $dailyUser->scores()->create([
        'game_slug' => 'maze',
        'score' => 300,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $weeklyUser->scores()->create([
        'game_slug' => 'maze',
        'score' => 400,
        'source' => 'manual',
        'achieved_at' => now()->subDays(3),
    ]);

    $oldUser->scores()->create([
        'game_slug' => 'maze',
        'score' => 500,
        'source' => 'manual',
        'achieved_at' => now()->subDays(10),
    ]);

    $this->getJson('/api/leaderboard/maze?period=daily')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $dailyUser->id);

    $this->getJson('/api/leaderboard/maze?period=weekly')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.user_id', $weeklyUser->id)
        ->assertJsonPath('data.1.user_id', $dailyUser->id);
});

it('returns the authenticated users rank and best score', function (): void {
    [$leader, $player] = User::factory()->count(2)->create();

    $leader->scores()->create([
        'game_slug' => 'arcade',
        'score' => 900,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $player->scores()->create([
        'game_slug' => 'arcade',
        'score' => 500,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $player->scores()->create([
        'game_slug' => 'arcade',
        'score' => 750,
        'source' => 'manual',
        'achieved_at' => now()->subMinute(),
    ]);

    $this->actingAs($player, 'sanctum')
        ->getJson('/api/leaderboard/arcade/me')
        ->assertOk()
        ->assertJsonPath('data.rank', 2)
        ->assertJsonPath('data.best_score', 750);
});

it('invalidates leaderboard cache for one requested period', function (): void {
    Cache::put('leaderboard.arcade.daily', collect(['stale']), 60);
    Cache::put('leaderboard.arcade.weekly', collect(['stale']), 60);
    Cache::put('leaderboard.arcade.alltime', collect(['stale']), 60);

    Sanctum::actingAs(User::factory()->create(), ['leaderboard:invalidate']);

    $this->postJson('/api/leaderboard/arcade/invalidate', [
        'period' => 'weekly',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Leaderboard cache invalidated.');

    expect(Cache::has('leaderboard.arcade.daily'))->toBeTrue()
        ->and(Cache::has('leaderboard.arcade.weekly'))->toBeFalse()
        ->and(Cache::has('leaderboard.arcade.alltime'))->toBeTrue();
});

it('archives daily snapshots and prunes old snapshots', function (): void {
    $users = User::factory()->count(11)->create();

    foreach ($users as $index => $user) {
        $user->scores()->create([
            'game_slug' => 'arcade',
            'score' => 1000 - $index,
            'source' => 'manual',
            'achieved_at' => now()->subMinutes($index),
        ]);
    }

    LeaderboardSnapshot::query()->create([
        'game_slug' => 'arcade',
        'period' => 'daily',
        'snapshot_date' => now()->subDays(91)->toDateString(),
        'data' => [],
    ]);

    $this->artisan('leaderboard:archive')
        ->expectsOutput('Archived arcade leaderboard with 10 entrie(s).')
        ->expectsOutput('Deleted 1 old leaderboard snapshot(s).')
        ->assertSuccessful();

    $snapshot = LeaderboardSnapshot::query()
        ->where('game_slug', 'arcade')
        ->where('period', 'daily')
        ->whereDate('snapshot_date', now()->toDateString())
        ->firstOrFail();

    expect($snapshot->data)->toHaveCount(10)
        ->and($snapshot->data[0])->toMatchArray([
            'rank' => 1,
            'user_id' => $users[0]->id,
            'score' => 1000,
        ])
        ->and(LeaderboardSnapshot::query()
            ->whereDate('snapshot_date', now()->subDays(91)->toDateString())
            ->exists())->toBeFalse();
});
