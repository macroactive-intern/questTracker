<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QuestXpCounterService
{
    public function submitQuestXp(int $userId, int $xpReward): int
    {
        $totalXp = (int) Cache::increment($this->cacheKey($userId), $xpReward);

        Log::channel('xp')->info('Quest XP submitted.', [
            'user_id' => $userId,
            'xp_reward' => $xpReward,
            'total_xp' => $totalXp,
        ]);

        return $totalXp;
    }

    public function totalXpForUser(int $userId): int
    {
        return (int) Cache::get($this->cacheKey($userId), 0);
    }

    private function cacheKey(int $userId): string
    {
        return "leaderboard:user:{$userId}:xp";
    }
}
