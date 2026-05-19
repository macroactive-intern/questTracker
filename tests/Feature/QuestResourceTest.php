<?php

use App\Http\Resources\QuestResource;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('serializes a quest with owner and counted subquests', function (): void {
    $user = User::factory()->create(['name' => 'Hadlee']);

    $quest = Quest::create([
        'user_id' => $user->id,
        'title' => 'Recover the sunstone',
        'description' => 'Find the missing relic.',
        'status' => 'open',
        'xp_reward' => 250,
        'due_at' => now()->addDay(),
    ]);

    Quest::create([
        'user_id' => $user->id,
        'parent_id' => $quest->id,
        'title' => 'Search the archive',
    ]);

    $quest = Quest::query()
        ->with('owner')
        ->withCount('subQuests')
        ->findOrFail($quest->id);

    $resource = (new QuestResource($quest))->resolve(Request::create('/'));

    expect($resource)->toMatchArray([
        'id' => $quest->id,
        'title' => 'Recover the sunstone',
        'description' => 'Find the missing relic.',
        'status' => 'open',
        'xp_reward' => 250,
        'sub_quest_count' => 1,
        'owner' => [
            'id' => $user->id,
            'name' => 'Hadlee',
        ],
    ])
        ->and($resource['due_at'])->not->toBeNull()
        ->and($resource['created_at'])->not->toBeNull()
        ->and($resource)->not->toHaveKey('sub_quests');
});

it('includes nested subquests only when loaded', function (): void {
    $user = User::factory()->create();

    $parent = Quest::create([
        'user_id' => $user->id,
        'title' => 'Parent quest',
    ]);

    Quest::create([
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'title' => 'Nested quest',
    ]);

    $withoutSubQuests = (new QuestResource($parent))->resolve(Request::create('/'));

    $withSubQuests = (new QuestResource(
        $parent->fresh()->load('subQuests')
    ))->resolve(Request::create('/'));

    expect($withoutSubQuests)->not->toHaveKey('sub_quests')
        ->and($withSubQuests)->toHaveKey('sub_quests')
        ->and($withSubQuests['sub_quests'])->toHaveCount(1)
        ->and($withSubQuests['sub_quests'][0]['title'])->toBe('Nested quest');
});
