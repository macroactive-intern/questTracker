<?php

namespace App\Repositories;

use App\Models\LeaderboardSnapshot;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

class LeaderboardSnapshotRepository
{
    private const DAILY_SNAPSHOT_RETENTION_DAYS = 90;
    private const WEEKLY_SNAPSHOT_RETENTION_DAYS = 730;

    /**
     * @param array<int, array<string, mixed>> $data
     */
    public function storeDailySnapshot(string $slug, Carbon $snapshotDate, array $data): LeaderboardSnapshot
    {
        return LeaderboardSnapshot::query()->updateOrCreate(
            [
                'game_slug' => $slug,
                'period' => 'daily',
                'snapshot_date' => $snapshotDate,
            ],
            ['data' => $data],
        );
    }

    /**
     * @return EloquentCollection<int, LeaderboardSnapshot>
     */
    public function latestDailySnapshots(string $slug, int $limit = 30): EloquentCollection
    {
        return LeaderboardSnapshot::query()
            ->where('game_slug', $slug)
            ->where('period', 'daily')
            ->latest('snapshot_date')
            ->limit($limit)
            ->get();
    }

    public function pruneOldSnapshots(): int
    {
        $dailyDeleted = LeaderboardSnapshot::query()
            ->where('period', 'daily')
            ->whereDate('snapshot_date', '<', Carbon::today()->subDays(self::DAILY_SNAPSHOT_RETENTION_DAYS))
            ->delete();

        $weeklyDeleted = LeaderboardSnapshot::query()
            ->where('period', 'weekly')
            ->whereDate('snapshot_date', '<', Carbon::today()->subDays(self::WEEKLY_SNAPSHOT_RETENTION_DAYS))
            ->delete();

        return $dailyDeleted + $weeklyDeleted;
    }
}
