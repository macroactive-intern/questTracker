<?php

namespace App\Listeners;

use App\Events\QuestCompleted;
use App\Services\LeaderboardService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SubmitQuestXpToLeaderboard implements ShouldQueue
{
    public function __construct(
        private readonly LeaderboardService $leaderboard,
    ) {
    }

    public function handle(QuestCompleted $event): void
    {
        $this->leaderboard->submitQuestXp(
            userId: $event->quest->user_id,
            xpReward: $event->quest->xp_reward,
        );
    }
}
