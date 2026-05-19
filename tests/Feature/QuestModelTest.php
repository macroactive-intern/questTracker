<?php

use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('connects quests to users and subquests', function (): void {
    $user = User::factory()->create();

    $parent = Quest::create([
        'user_id' => $user->id,
        'title' => 'Recover the lost map',
        'status' => 'in_progress',
        'xp_reward' => 100,
        'due_at' => now()->addDay(),
    ]);

    $subQuest = Quest::create([
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'title' => 'Question the tavern keeper',
    ]);

    expect($parent->user->is($user))->toBeTrue()
        ->and($user->quests)->toHaveCount(2)
        ->and($subQuest->parent->is($parent))->toBeTrue()
        ->and($parent->subQuests)->toHaveCount(1)
        ->and($parent->xp_reward)->toBe(100)
        ->and($parent->due_at)->not->toBeNull();
});

it('cascades deletes to subquests when a parent quest is deleted', function (): void {
    $user = User::factory()->create();

    $parent = Quest::create([
        'user_id' => $user->id,
        'title' => 'Clear the old mine',
    ]);

    Quest::create([
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'title' => 'Find the missing pickaxe',
    ]);

    $parent->delete();

    expect(Quest::count())->toBe(0);
});
