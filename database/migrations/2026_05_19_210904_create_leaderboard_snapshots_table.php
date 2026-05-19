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
        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('game_slug')->index();
            $table->enum('period', ['daily', 'weekly']);
            $table->date('snapshot_date')->index();
            $table->json('data');
            $table->timestamps();

            // Guarantees one snapshot per game/period/date and makes the hot lookup
            // path for rendering a specific leaderboard snapshot use a single index.
            $table->unique(['game_slug', 'period', 'snapshot_date']);

            // Supports batch jobs and archive pages that list all snapshots for a
            // period/date across games without scanning unrelated snapshot rows.
            $table->index(['period', 'snapshot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboard_snapshots');
    }
};
