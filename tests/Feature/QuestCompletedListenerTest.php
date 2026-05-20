<?php

use App\Events\QuestCompleted;
use App\Listeners\SubmitQuestXpToLeaderboard;
use App\Models\Quest;
use App\Models\User;
use App\Repositories\ScoreRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('uses a queued listener for quest leaderboard submissions', function (): void {
    $listener = app(SubmitQuestXpToLeaderboard::class);

    expect($listener)->toBeInstanceOf(ShouldQueue::class);
});

it('submits completed quest xp as a leaderboard score', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $user = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $user->id,
        'title' => 'Complete the trial',
        'status' => 'completed',
        'xp_reward' => 75,
    ]);

    $listener = app(SubmitQuestXpToLeaderboard::class);

    $listener->handle(new QuestCompleted($quest));

    $this->assertDatabaseHas('scores', [
        'user_id' => $user->id,
        'game_slug' => 'quests',
        'score' => 75,
        'source' => 'quest_completion',
        'achieved_at' => now(),
    ]);
    $this->assertDatabaseHas('quest_xp_totals', [
        'user_id' => $user->id,
        'total_xp' => 75,
    ]);

    Carbon::setTestNow();
});

it('rolls back the xp counter when the score insert fails', function (): void {
    $user = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $user->id,
        'title' => 'Complete the trial',
        'status' => 'completed',
        'xp_reward' => 75,
    ]);

    $mockRepo = Mockery::mock(ScoreRepository::class);
    $mockRepo->shouldReceive('submitScore')->andThrow(new RuntimeException('DB failure'));
    app()->instance(ScoreRepository::class, $mockRepo);

    $listener = app(SubmitQuestXpToLeaderboard::class);

    expect(fn () => $listener->handle(new QuestCompleted($quest)))
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseMissing('quest_xp_totals', ['user_id' => $user->id]);
});

it('uses a quest game slug when one is present on the event model', function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');

    $user = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $user->id,
        'title' => 'Complete the trial',
        'status' => 'completed',
        'xp_reward' => 125,
    ]);
    $quest->setAttribute('game_slug', 'arcade');

    $listener = app(SubmitQuestXpToLeaderboard::class);

    $listener->handle(new QuestCompleted($quest));

    $this->assertDatabaseHas('scores', [
        'user_id' => $user->id,
        'game_slug' => 'arcade',
        'score' => 125,
        'source' => 'quest_completion',
        'achieved_at' => now(),
    ]);
    $this->assertDatabaseHas('quest_xp_totals', [
        'user_id' => $user->id,
        'total_xp' => 125,
    ]);

    Carbon::setTestNow();
});
