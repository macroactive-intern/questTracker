<?php

use App\Events\QuestCompleted;
use App\Listeners\SubmitQuestXpToLeaderboard;
use App\Models\Quest;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('uses a queued listener for quest leaderboard submissions', function (): void {
    $listener = new SubmitQuestXpToLeaderboard(app(LeaderboardService::class));

    expect($listener)->toBeInstanceOf(ShouldQueue::class);
});

it('logs completed quest xp to the xp log', function (): void {
    $logPath = storage_path('logs/xp.log');

    if (file_exists($logPath)) {
        unlink($logPath);
    }

    $user = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $user->id,
        'title' => 'Complete the trial',
        'status' => 'completed',
        'xp_reward' => 75,
    ]);

    $listener = new SubmitQuestXpToLeaderboard(app(LeaderboardService::class));

    $listener->handle(new QuestCompleted($quest));

    expect(file_get_contents($logPath))
        ->toContain('"user_id":'.$user->id)
        ->toContain('"xp_reward":75')
        ->toContain('"total_xp":75');
});

it('tracks cumulative leaderboard xp per user', function (): void {
    Cache::flush();

    $leaderboard = app(LeaderboardService::class);
    $userId = User::factory()->create()->id;

    expect($leaderboard->submitQuestXp($userId, 75))->toBe(75)
        ->and($leaderboard->submitQuestXp($userId, 25))->toBe(100)
        ->and($leaderboard->totalXpForUser($userId))->toBe(100);
});
