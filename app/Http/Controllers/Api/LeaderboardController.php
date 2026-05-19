<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaderboardEntryResource;
use App\Models\LeaderboardSnapshot;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboard,
    ) {
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_slug' => ['required', 'string', 'max:255'],
            'score' => ['required', 'numeric', 'min:0'],
        ]);

        $score = $this->leaderboard->submit([
            ...$data,
            'user_id' => $request->user()->id,
            'score' => (int) $data['score'],
            'source' => 'manual',
            'achieved_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $score->id,
                'user_id' => $score->user_id,
                'game_slug' => $score->game_slug,
                'score' => $score->score,
                'source' => $score->source,
                'achieved_at' => $score->achieved_at?->toJSON(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function index(Request $request, string $slug): AnonymousResourceCollection
    {
        $data = $request->validate([
            'period' => ['sometimes', Rule::in(['daily', 'weekly', 'alltime'])],
        ]);

        return LeaderboardEntryResource::collection(
            $this->leaderboard->getLeaderboard($slug, $data['period'] ?? 'alltime', 10),
        );
    }

    public function history(string $slug): JsonResponse
    {
        $snapshots = LeaderboardSnapshot::query()
            ->where('game_slug', $slug)
            ->where('period', 'daily')
            ->latest('snapshot_date')
            ->limit(30)
            ->get()
            ->map(fn (LeaderboardSnapshot $snapshot): array => [
                'snapshot_date' => $snapshot->snapshot_date?->toDateString(),
                'data' => $snapshot->data,
                'created_at' => $snapshot->created_at?->toJSON(),
            ]);

        return response()->json(['data' => $snapshots]);
    }

    public function me(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'period' => ['sometimes', Rule::in(['daily', 'weekly', 'alltime'])],
        ]);

        $rank = $this->leaderboard->getUserRank(
            $slug,
            $request->user()->id,
            $data['period'] ?? 'alltime',
        );

        return response()->json([
            'data' => [
                'rank' => $rank?->rank,
                'best_score' => $rank?->score,
            ],
        ]);
    }

    public function invalidate(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'period' => ['sometimes', Rule::in(['daily', 'weekly', 'alltime'])],
        ]);

        $this->leaderboard->invalidate($slug, $data['period'] ?? null);

        return response()->json([
            'message' => 'Leaderboard cache invalidated.',
        ]);
    }
}
