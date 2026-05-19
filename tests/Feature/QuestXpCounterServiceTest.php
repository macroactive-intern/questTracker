<?php

use App\Services\QuestXpCounterService;
use Illuminate\Support\Facades\Cache;

it('tracks cumulative quest xp independently from leaderboard rankings', function (): void {
    Cache::flush();

    $service = app(QuestXpCounterService::class);

    expect($service->submitQuestXp(10, 75))->toBe(75)
        ->and($service->submitQuestXp(10, 25))->toBe(100)
        ->and($service->totalXpForUser(10))->toBe(100);
});
