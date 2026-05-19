<?php

namespace App\Console\Commands;

use App\Enums\LeaderboardPeriod;
use App\Repositories\LeaderboardSnapshotRepository;
use App\Repositories\ScoreRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ArchiveLeaderboard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leaderboard:archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive daily leaderboard snapshots and prune old snapshots.';

    public function __construct(
        private readonly ScoreRepository $scores,
        private readonly LeaderboardSnapshotRepository $snapshots,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $slugs = $this->scores->distinctGameSlugs();

        if ($slugs->isEmpty()) {
            $this->info('No game slugs found. Nothing to archive.');

            return self::SUCCESS;
        }

        $archiveWeekly = $today->isMonday();

        $this->info("Archiving daily leaderboard snapshots for {$slugs->count()} game(s).");

        if ($archiveWeekly) {
            $this->info("Archiving weekly leaderboard snapshots for {$slugs->count()} game(s).");
        }

        foreach ($slugs as $slug) {
            $leaderboard = $this->scores->topPlayers($slug, LeaderboardPeriod::Daily->value, 10);

            $this->snapshots->storeDailySnapshot(
                $slug,
                $today,
                $this->snapshotData($leaderboard),
            );

            $this->line("Archived {$slug} leaderboard with {$leaderboard->count()} entrie(s).");

            if ($archiveWeekly) {
                $weeklyLeaderboard = $this->scores->topPlayers($slug, LeaderboardPeriod::Weekly->value, 10);

                $this->snapshots->storeWeeklySnapshot(
                    $slug,
                    $today,
                    $this->snapshotData($weeklyLeaderboard),
                );

                $this->line("Archived {$slug} weekly leaderboard with {$weeklyLeaderboard->count()} entrie(s).");
            }
        }

        $deleted = $this->snapshots->pruneOldSnapshots();

        $this->info("Deleted {$deleted} old leaderboard snapshot(s).");
        $this->info('Leaderboard archive complete.');

        return self::SUCCESS;
    }

    /**
     * @param Collection<int, object> $leaderboard
     * @return array<int, array{rank: int, user_id: int, score: int, achieved_at: mixed}>
     */
    private function snapshotData(Collection $leaderboard): array
    {
        return $leaderboard
            ->map(fn (object $entry): array => [
                'rank' => (int) $entry->rank,
                'user_id' => (int) $entry->user_id,
                'score' => (int) $entry->score,
                'achieved_at' => $entry->achieved_at,
            ])
            ->values()
            ->all();
    }
}
