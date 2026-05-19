<?php

namespace App\Services;

use App\Enums\LeaderboardPeriod;
use App\Models\Score;
use App\Repositories\ScoreRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LeaderboardService
{
    private const CACHE_TTL_SECONDS = 60;
    private const LOCK_TTL_SECONDS = 10;
    private const LOCK_WAIT_MICROSECONDS = 150_000;

    public function __construct(
        private readonly ScoreRepository $scores,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(array $data): Score
    {
        $score = $this->scores->submitScore($data);

        $this->invalidate($score->game_slug);

        return $score;
    }

    /**
     * @return Collection<int, object>
     */
    public function getLeaderboard(string $slug, string $period = 'alltime', int $limit = 10): Collection
    {
        $cacheKey = $this->cacheKey($slug, $period);
        $cachedLeaderboard = Cache::get($cacheKey);

        if ($cachedLeaderboard !== null) {
            return $cachedLeaderboard;
        }

        $lock = Cache::lock($this->lockKey($slug, $period), self::LOCK_TTL_SECONDS);

        if ($lock->get()) {
            try {
                return Cache::remember(
                    $cacheKey,
                    self::CACHE_TTL_SECONDS,
                    fn () => $this->scores->topPlayers($slug, $period, $limit),
                );
            } finally {
                $lock->release();
            }
        }

        // A cache stampede happens when many requests miss the same cache key at
        // once and all rebuild it, causing a sudden burst of duplicate DB work.
        // The atomic lock lets only one request run the expensive leaderboard
        // query while the rest briefly pause and then reuse the freshly cached
        // result, preventing simultaneous hits against the scores table.
        usleep(self::LOCK_WAIT_MICROSECONDS);

        return Cache::get($cacheKey, collect());
    }

    public function getUserRank(string $slug, int $userId, string $period = 'alltime'): ?object
    {
        return $this->scores->userRank($slug, $userId, $period);
    }

    public function invalidate(string $slug, ?string $period = null): void
    {
        foreach ($period === null ? LeaderboardPeriod::values() : [$period] as $cachePeriod) {
            Cache::forget($this->cacheKey($slug, $cachePeriod));
        }
    }

    private function cacheKey(string $slug, string $period): string
    {
        return "leaderboard.{$slug}.{$period}";
    }

    private function lockKey(string $slug, string $period): string
    {
        return "leaderboard-building.{$slug}.{$period}";
    }
}
