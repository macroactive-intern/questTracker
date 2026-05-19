<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            // Supports daily/weekly leaderboards by filtering first on game_slug
            // and achieved_at, then reading user_id/score from the same index for
            // the grouped MAX(score) query instead of scanning all rows for a game.
            $table->index(
                ['game_slug', 'achieved_at', 'user_id', 'score'],
                'scores_game_achieved_user_score_idx',
            );

            // Supports all-time leaderboards and the best-score join path by
            // keeping rows ordered by game_slug, user_id, score, and achieved_at.
            // This helps the database group by user_id after filtering by game,
            // then match each user's MAX(score) back to its achieved_at timestamp.
            $table->index(
                ['game_slug', 'user_id', 'score', 'achieved_at'],
                'scores_game_user_score_achieved_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->dropIndex('scores_game_achieved_user_score_idx');
            $table->dropIndex('scores_game_user_score_achieved_idx');
        });
    }
};
