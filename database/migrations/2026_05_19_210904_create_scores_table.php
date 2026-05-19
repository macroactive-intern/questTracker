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
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game_slug');
            $table->unsignedBigInteger('score');
            $table->enum('source', ['manual', 'quest_completion']);
            $table->timestamp('achieved_at');
            $table->timestamps();

            // Supports the main leaderboard query: filter by game, scan top scores,
            // and break ties by most recent achievement without sorting the whole table.
            $table->index(['game_slug', 'score', 'achieved_at']);

            // Supports player profile/history lookups and de-duplication checks per game.
            $table->index(['user_id', 'game_slug', 'achieved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
