<?php

namespace App\Console\Commands;

use App\Enums\LeaderboardPeriod;
use App\Repositories\LeaderboardSnapshotRepository;
use App\Repositories\ScoreRepository;
use App\Services\LeaderboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

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
        private readonly LeaderboardService $leaderboard,
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
        }

        $this->info("Archiving daily leaderboard snapshots for {$slugs->count()} game(s).");

        foreach ($slugs as $slug) {
            $leaderboard = $this->leaderboard->getLeaderboard($slug, LeaderboardPeriod::Daily->value, 10);

            $this->snapshots->storeDailySnapshot(
                $slug,
                $today,
                $leaderboard
                    ->map(fn (object $entry): array => [
                        'rank' => (int) $entry->rank,
                        'user_id' => (int) $entry->user_id,
                        'score' => (int) $entry->score,
                        'achieved_at' => $entry->achieved_at,
                    ])
                    ->values()
                    ->all(),
            );

            $this->line("Archived {$slug} leaderboard with {$leaderboard->count()} entrie(s).");
        }

        $deleted = $this->snapshots->pruneOldSnapshots();

        $this->info("Deleted {$deleted} old leaderboard snapshot(s).");
        $this->info('Leaderboard archive complete.');

        return self::SUCCESS;
    }
}
