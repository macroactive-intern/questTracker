<?php

namespace App\Services;

use App\Models\LeaderboardSnapshot;
use App\Repositories\LeaderboardSnapshotRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeaderboardSnapshotService
{
    public function __construct(
        private readonly LeaderboardSnapshotRepository $snapshots,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    public function storeDailySnapshot(string $slug, Carbon $snapshotDate, array $data): LeaderboardSnapshot
    {
        return $this->snapshots->storeDailySnapshot($slug, $snapshotDate, $data);
    }

    /**
     * @return Collection<int, array{snapshot_date: string|null, data: mixed, created_at: string|null}>
     */
    public function latestDailySnapshotData(string $slug, int $limit = 30): Collection
    {
        return $this->snapshots->latestDailySnapshotData($slug, $limit);
    }

    public function pruneOldSnapshots(): int
    {
        return $this->snapshots->pruneOldSnapshots();
    }
}
