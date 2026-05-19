<?php

use App\Http\Resources\LeaderboardEntryResource;
use App\Models\Score;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('serializes aggregate leaderboard rows cleanly', function (): void {
    $row = (object) [
        'rank' => '2',
        'user_id' => '15',
        'score' => '9000',
        'achieved_at' => '2026-05-20 10:30:00',
    ];

    $resource = (new LeaderboardEntryResource($row))->resolve(Request::create('/'));

    expect($resource)->toBe([
        'rank' => 2,
        'user_id' => 15,
        'score' => 9000,
        'achieved_at' => Carbon::parse('2026-05-20 10:30:00')->toJSON(),
    ]);
});

it('handles missing aggregate achieved at values as null', function (): void {
    $row = (object) [
        'rank' => 1,
        'user_id' => 10,
        'score' => 500,
    ];

    $resource = (new LeaderboardEntryResource($row))->resolve(Request::create('/'));

    expect($resource)->toBe([
        'rank' => 1,
        'user_id' => 10,
        'score' => 500,
        'achieved_at' => null,
    ]);
});

it('serializes score models with date casts', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $score = Score::query()->create([
        'user_id' => User::factory()->create()->id,
        'game_slug' => 'arcade',
        'score' => 700,
        'source' => 'manual',
        'achieved_at' => now(),
    ]);

    $score->rank = 3;

    $resource = (new LeaderboardEntryResource($score))->resolve(Request::create('/'));

    expect($resource)->toBe([
        'rank' => 3,
        'user_id' => $score->user_id,
        'score' => 700,
        'achieved_at' => now()->toJSON(),
    ]);

    Carbon::setTestNow();
});
