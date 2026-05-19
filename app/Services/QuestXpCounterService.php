<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestXpCounterService
{
    private const CACHE_TTL_SECONDS = 300;

    public function submitQuestXp(int $userId, int $xpReward): int
    {
        $now = now();

        DB::table('quest_xp_totals')->upsert(
            [
                [
                    'user_id' => $userId,
                    'total_xp' => $xpReward,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['user_id'],
            [
                'total_xp' => DB::raw('total_xp + '.(int) $xpReward),
                'updated_at' => $now,
            ],
        );

        $totalXp = $this->totalXpFromDatabase($userId);

        Cache::put($this->cacheKey($userId), $totalXp, self::CACHE_TTL_SECONDS);

        Log::channel('xp')->info('Quest XP submitted.', [
            'user_id' => $userId,
            'xp_reward' => $xpReward,
            'total_xp' => $totalXp,
        ]);

        return $totalXp;
    }

    public function totalXpForUser(int $userId): int
    {
        return (int) Cache::remember(
            $this->cacheKey($userId),
            self::CACHE_TTL_SECONDS,
            fn (): int => $this->totalXpFromDatabase($userId),
        );
    }

    private function totalXpFromDatabase(int $userId): int
    {
        return (int) DB::table('quest_xp_totals')
            ->where('user_id', $userId)
            ->value('total_xp');
    }

    private function cacheKey(int $userId): string
    {
        return "leaderboard.user.{$userId}.xp";
    }
}
