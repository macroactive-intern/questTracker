<?php

namespace App\Repositories;

use App\Enums\LeaderboardPeriod;
use App\Models\LeaderboardSnapshot;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
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
        return $this->storeSnapshot($slug, LeaderboardPeriod::Daily, $snapshotDate, $data);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    public function storeWeeklySnapshot(string $slug, Carbon $snapshotDate, array $data): LeaderboardSnapshot
    {
        return $this->storeSnapshot($slug, LeaderboardPeriod::Weekly, $snapshotDate, $data);
    }

    /**
     * @return EloquentCollection<int, LeaderboardSnapshot>
     */
    public function latestDailySnapshots(string $slug, int $limit = 30): EloquentCollection
    {
        return LeaderboardSnapshot::query()
            ->where('game_slug', $slug)
            ->where('period', LeaderboardPeriod::Daily->value)
            ->latest('snapshot_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, array{snapshot_date: string|null, data: mixed, created_at: string|null}>
     */
    public function latestDailySnapshotData(string $slug, int $limit = 30): Collection
    {
        return $this->latestDailySnapshots($slug, $limit)
            ->map(fn (LeaderboardSnapshot $snapshot): array => [
                'snapshot_date' => $snapshot->snapshot_date?->toDateString(),
                'data' => $snapshot->data,
                'created_at' => $snapshot->created_at?->toJSON(),
            ]);
    }

    public function pruneOldSnapshots(): int
    {
        $dailyDeleted = LeaderboardSnapshot::query()
            ->where('period', LeaderboardPeriod::Daily->value)
            ->whereDate('snapshot_date', '<', Carbon::today()->subDays(self::DAILY_SNAPSHOT_RETENTION_DAYS))
            ->delete();

        $weeklyDeleted = LeaderboardSnapshot::query()
            ->where('period', LeaderboardPeriod::Weekly->value)
            ->whereDate('snapshot_date', '<', Carbon::today()->subDays(self::WEEKLY_SNAPSHOT_RETENTION_DAYS))
            ->delete();

        return $dailyDeleted + $weeklyDeleted;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    private function storeSnapshot(
        string $slug,
        LeaderboardPeriod $period,
        Carbon $snapshotDate,
        array $data,
    ): LeaderboardSnapshot {
        return LeaderboardSnapshot::query()->updateOrCreate(
            [
                'game_slug' => $slug,
                'period' => $period->value,
                'snapshot_date' => $snapshotDate,
            ],
            ['data' => $data],
        );
    }
}
