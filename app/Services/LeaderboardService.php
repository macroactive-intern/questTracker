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
        $cached = Cache::get($cacheKey);

        if ($cached instanceof Collection) {
            return $cached;
        }

        $lock = Cache::lock($this->lockKey($slug, $period), self::LOCK_TTL_SECONDS);

        if ($lock->get()) {
            try {
                $cached = Cache::get($cacheKey);

                if ($cached instanceof Collection) {
                    return $cached;
                }

                $leaderboard = $this->scores->topPlayers($slug, $period, $limit);
                Cache::put($cacheKey, $leaderboard, self::CACHE_TTL_SECONDS);

                return $leaderboard;
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

        $cached = Cache::get($cacheKey);

        if ($cached instanceof Collection) {
            return $cached;
        }

        return $this->scores->topPlayers($slug, $period, $limit);
    }

    public function getUserRank(string $slug, int $userId, string $period = 'alltime'): ?object
    {
        $key = $this->rankCacheKey($slug, $period, $userId);
        $cached = Cache::get($key);

        if ($cached !== null) {
            // false is the sentinel for a confirmed no-rank result so that
            // unranked users are not re-queried on every request.
            return $cached === false ? null : $cached;
        }

        $rank = $this->scores->userRank($slug, $userId, $period);
        Cache::put($key, $rank ?? false, self::CACHE_TTL_SECONDS);

        return $rank;
    }

    public function invalidate(string $slug, ?string $period = null): void
    {
        foreach ($period === null ? LeaderboardPeriod::values() : [$period] as $cachePeriod) {
            Cache::forget($this->cacheKey($slug, $cachePeriod));
            // Atomically bump the version so all rank cache keys for this
            // slug/period are abandoned without needing to enumerate user IDs.
            Cache::increment($this->rankVersionKey($slug, $cachePeriod));
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

    private function rankCacheKey(string $slug, string $period, int $userId): string
    {
        // The version is read once per lookup. When invalidate() increments it,
        // the old versioned keys are naturally abandoned and expire after TTL.
        $version = (int) Cache::get($this->rankVersionKey($slug, $period), 0);

        return "leaderboard-rank.{$slug}.{$period}.v{$version}.{$userId}";
    }

    private function rankVersionKey(string $slug, string $period): string
    {
        return "leaderboard-rank-version.{$slug}.{$period}";
    }
}
