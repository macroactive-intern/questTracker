<?php

use App\Http\Resources\QuestCollection;
use App\Http\Resources\UserResource;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('serializes users with a clean public shape', function (): void {
    $user = User::factory()->create([
        'name' => 'Hadlee',
        'email' => 'hidden@example.com',
    ]);

    $resource = (new UserResource($user))->resolve(Request::create('/'));

    expect($resource)->toBe([
        'id' => $user->id,
        'name' => 'Hadlee',
    ]);
});

it('serializes paginated quest collections with metadata and links', function (): void {
    $user = User::factory()->create(['name' => 'Hadlee']);

    foreach (range(1, 16) as $index) {
        Quest::create([
            'user_id' => $user->id,
            'title' => "Quest {$index}",
        ]);
    }

    $paginator = Quest::query()
        ->with('owner')
        ->withCount('subQuests')
        ->orderBy('id')
        ->paginate(15, ['*'], 'page', 1);

    $collection = (new QuestCollection($paginator))->toArray(Request::create('/api/quests'));

    expect($collection['data'])->toHaveCount(15)
        ->and($collection['data'][0])->toMatchArray([
            'title' => 'Quest 1',
            'owner' => [
                'id' => $user->id,
                'name' => 'Hadlee',
            ],
            'sub_quest_count' => 0,
        ])
        ->and($collection['meta'])->toMatchArray([
            'current_page' => 1,
            'last_page' => 2,
            'per_page' => 15,
            'total' => 16,
        ])
        ->and($collection['links'])->toHaveKeys(['first', 'last', 'prev', 'next'])
        ->and($collection['links']['next'])->not->toBeNull();
});
