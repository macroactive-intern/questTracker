<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LeaderboardService
{
    public function submitQuestXp(int $userId, int $xpReward): void
    {
        Log::channel('xp')->info('Quest XP submitted to leaderboard.', [
            'user_id' => $userId,
            'xp_reward' => $xpReward,
        ]);
    }
}
