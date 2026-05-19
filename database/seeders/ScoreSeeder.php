<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScoreSeeder extends Seeder
{
    private const TOTAL_ROWS = 150_000;
    private const BATCH_SIZE = 1_000;

    /**
     * @var list<string>
     */
    private const GAME_SLUGS = [
        'arcade-blitz',
        'maze-runner',
        'quest-climb',
        'memory-match',
        'speed-sort',
        'tower-trial',
        'puzzle-rush',
        'arena-dash',
        'word-sprint',
        'logic-ladder',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();

        if ($userIds === []) {
            $this->command?->warn('ScoreSeeder skipped: create users before seeding scores.');

            return;
        }

        $this->command?->info('Seeding 150,000 score rows in batches of 1,000...');

        $inserted = 0;
        // Measure total wall-clock time for the bulk load; this gives a useful
        // baseline when comparing index, disk, or database engine performance.
        $startedAt = microtime(true);

        while ($inserted < self::TOTAL_ROWS) {
            $batchSize = min(self::BATCH_SIZE, self::TOTAL_ROWS - $inserted);
            $rows = [];

            for ($index = 0; $index < $batchSize; $index++) {
                $rows[] = $this->scoreRow($userIds);
            }

            // Performance notes:
            // - DB::table()->insert() sends one bulk SQL statement per 1,000 rows,
            //   avoiding the per-row model hydration/events overhead of Eloquent.
            // - Batches of 1,000 cap PHP memory while reducing database round trips
            //   from 150,000 individual inserts to 150 bulk inserts.
            // - Timestamps and random values are generated in PHP before each bulk
            //   insert so the database can focus on writing indexed score rows.
            DB::table('scores')->insert($rows);

            $inserted += $batchSize;
            $this->command?->line("Inserted {$inserted}/".self::TOTAL_ROWS.' score rows.');
        }

        $duration = round(microtime(true) - $startedAt, 2);

        $this->command?->info("ScoreSeeder complete in {$duration}s.");
    }

    /**
     * @param list<int> $userIds
     *
     * @return array<string, mixed>
     */
    private function scoreRow(array $userIds): array
    {
        $now = now();

        return [
            'user_id' => $userIds[array_rand($userIds)],
            'game_slug' => self::GAME_SLUGS[array_rand(self::GAME_SLUGS)],
            'score' => $this->realisticScore(),
            'source' => mt_rand(1, 100) <= 70 ? 'quest_completion' : 'manual',
            'achieved_at' => $now->copy()
                ->subDays(mt_rand(0, 89))
                ->subSeconds(mt_rand(0, 86_399)),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function realisticScore(): int
    {
        $baseScore = mt_rand(250, 25_000);
        $bonus = mt_rand(1, 100) <= 15 ? mt_rand(10_000, 75_000) : 0;

        return $baseScore + $bonus;
    }
}
