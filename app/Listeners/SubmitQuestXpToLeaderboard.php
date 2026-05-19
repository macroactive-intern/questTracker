<?php

namespace App\Listeners;

use App\Events\QuestCompleted;
use App\Services\LeaderboardService;
use App\Services\QuestXpCounterService;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        $this->xpCounter->submitQuestXp(
            $event->quest->user_id,
            $event->quest->xp_reward,
        );

        $this->leaderboard->submit([
            'user_id' => $event->quest->user_id,
            'game_slug' => $event->quest->game_slug ?? self::DEFAULT_GAME_SLUG,
            'score' => $event->quest->xp_reward,
            'source' => 'quest_completion',
            'achieved_at' => now(),
        ]);
    }
}
