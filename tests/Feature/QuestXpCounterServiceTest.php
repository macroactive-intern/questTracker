<?php

use App\Services\QuestXpCounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('tracks cumulative quest xp independently from leaderboard rankings', function (): void {
    Cache::flush();

    $user = \App\Models\User::factory()->create();
    $service = app(QuestXpCounterService::class);

    expect($service->submitQuestXp($user->id, 75))->toBe(75)
        ->and($service->submitQuestXp($user->id, 25))->toBe(100)
        ->and($service->totalXpForUser($user->id))->toBe(100);
});

it('falls back to the database when the xp cache is missing', function (): void {
    Cache::flush();

    $user = \App\Models\User::factory()->create();
    $service = app(QuestXpCounterService::class);

    $service->submitQuestXp($user->id, 125);
    Cache::forget("leaderboard:user:{$user->id}:xp");

    expect($service->totalXpForUser($user->id))->toBe(125);
});

it('does not treat stale cached xp as the source of truth after submission', function (): void {
    Cache::flush();

    $user = \App\Models\User::factory()->create();
    $service = app(QuestXpCounterService::class);

    $service->submitQuestXp($user->id, 125);
    Cache::put("leaderboard:user:{$user->id}:xp", 5, 300);

    expect($service->submitQuestXp($user->id, 25))->toBe(150)
        ->and($service->totalXpForUser($user->id))->toBe(150);
});
