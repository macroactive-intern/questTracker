<?php

namespace App\Repositories;

use App\Models\Score;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScoreRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function submitScore(array $data): Score
    {
        return Score::query()->create($data);
    }

    /**
     * @return Collection<int, object>
     */
    public function topPlayers(string $slug, string $period = 'alltime', int $limit = 10): Collection
    {
        return DB::query()
            ->fromSub($this->rankedScoresQuery($slug, $period), 'ranked_scores')
            ->select([
                'ranked_scores.user_id',
                'ranked_scores.score',
                'ranked_scores.achieved_at',
                'ranked_scores.rank',
            ])
            ->orderBy('ranked_scores.rank')
            ->orderBy('ranked_scores.user_id')
            ->limit($limit)
            ->get();
    }

    public function userRank(string $slug, int $userId, string $period = 'alltime'): ?object
    {
        return DB::query()
            ->fromSub($this->rankedScoresQuery($slug, $period), 'ranked_scores')
            ->select([
                'ranked_scores.user_id',
                'ranked_scores.score',
                'ranked_scores.achieved_at',
                'ranked_scores.rank',
            ])
            ->where('ranked_scores.user_id', $userId)
            ->first();
    }

    /**
     * @return Collection<int, string>
     */
    public function distinctGameSlugs(): Collection
    {
        return Score::query()
            ->distinct()
            ->orderBy('game_slug')
            ->pluck('game_slug');
    }

    private function rankedScoresQuery(string $slug, string $period): QueryBuilder
    {
        return DB::query()
            ->fromSub($this->groupedScoresQuery($slug, $period), 'player_scores')
            ->select([
                'player_scores.user_id',
                'player_scores.score',
                'player_scores.achieved_at',
                DB::raw('RANK() OVER (ORDER BY player_scores.score DESC) as rank'),
            ]);
    }

    private function groupedScoresQuery(string $slug, string $period): EloquentBuilder
    {
        $bestScores = Score::query()
            ->where('game_slug', $slug);

        $this->applyPeriodFilter($bestScores, $period);

        $bestScores = $bestScores
            ->select('user_id')
            // MAX(score) keeps each player's personal best for the period, so one
            // weaker attempt cannot drag down their leaderboard position.
            ->selectRaw('MAX(score) as score')
            // GROUP BY user_id is required because users can submit many scores,
            // but leaderboard rows must represent each user exactly once.
            ->groupBy('user_id');

        $query = Score::query()
            ->joinSub($bestScores, 'best_scores', function ($join): void {
                $join->on('scores.user_id', '=', 'best_scores.user_id')
                    ->on('scores.score', '=', 'best_scores.score');
            })
            ->where('scores.game_slug', $slug);

        $this->applyPeriodFilter($query, $period, 'scores.achieved_at');

        return $query
            ->select('best_scores.user_id', 'best_scores.score')
            ->selectRaw('MAX(scores.achieved_at) as achieved_at')
            ->groupBy('best_scores.user_id', 'best_scores.score');
    }

    private function applyPeriodFilter(
        EloquentBuilder $query,
        string $period,
        string $column = 'achieved_at',
    ): void {
        match ($period) {
            'daily' => $query
                ->where($column, '>=', Carbon::today())
                ->where($column, '<', Carbon::tomorrow()),
            'weekly' => $query->where($column, '>=', Carbon::now()->subDays(7)),
            'alltime' => null,
            default => throw new InvalidArgumentException("Unsupported leaderboard period [{$period}]."),
        };
    }
}
