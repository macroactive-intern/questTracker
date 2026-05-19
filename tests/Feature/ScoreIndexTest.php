<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('does not keep redundant score indexes covered by leaderboard composites', function (): void {
    expect(Schema::hasIndex('scores', 'scores_game_slug_index'))->toBeFalse()
        ->and(Schema::hasIndex('scores', 'scores_game_slug_achieved_at_index'))->toBeFalse()
        ->and(Schema::hasIndex('scores', 'scores_game_achieved_user_score_idx'))->toBeTrue()
        ->and(Schema::hasIndex('scores', 'scores_game_user_score_achieved_idx'))->toBeTrue();
});
