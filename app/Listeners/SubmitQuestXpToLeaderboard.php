<?php

namespace App\Listeners;

use App\Events\QuestCompleted;
use App\Services\LeaderboardService;
use App\Services\QuestXpCounterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class SubmitQuestXpToLeaderboard implements ShouldQueue
{
    private const DEFAULT_GAME_SLUG = 'quests';

    public function __construct(
        private readonly LeaderboardService $leaderboard,
        private readonly QuestXpCounterService $xpCounter,
    ) {
    }

    public function handle(QuestCompleted $event): void
    {
        $userId = $event->quest->user_id;
        $xpReward = $event->quest->xp_reward;
        $gameSlug = $event->quest->game_slug ?? self::DEFAULT_GAME_SLUG;

        DB::transaction(function () use ($userId, $xpReward, $gameSlug): void {
            $this->xpCounter->submitQuestXp($userId, $xpReward);

            $this->leaderboard->submit([
                'user_id' => $userId,
                'game_slug' => $gameSlug,
                'score' => $xpReward,
                'source' => 'quest_completion',
                'achieved_at' => now(),
            ]);
        });
    }
}
