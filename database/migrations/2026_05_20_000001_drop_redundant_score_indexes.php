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
        // Redundant with scores_game_achieved_user_score_idx because game_slug
        // is the leftmost column of that broader leaderboard index.
        if (Schema::hasIndex('scores', 'scores_game_slug_index')) {
            Schema::table('scores', function (Blueprint $table) {
                $table->dropIndex('scores_game_slug_index');
            });
        }

        // Redundant with scores_game_achieved_user_score_idx because the query
        // planner can use the leftmost (game_slug, achieved_at) prefix of the
        // broader composite index for daily/weekly filters.
        if (Schema::hasIndex('scores', 'scores_game_slug_achieved_at_index')) {
            Schema::table('scores', function (Blueprint $table) {
                $table->dropIndex('scores_game_slug_achieved_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->index('game_slug');
            $table->index(['game_slug', 'achieved_at']);
        });
    }
};
