<?php

use App\Models\Quest;
use App\Models\User;
use App\Repositories\Contracts\QuestRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function questRepository(): QuestRepositoryInterface
{
    return app(QuestRepositoryInterface::class);
}

it('paginates quests for a user with owner eager loaded and subquest counts', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $parent = Quest::create([
        'user_id' => $user->id,
        'title' => 'Gather herbs',
        'status' => 'open',
        'xp_reward' => 25,
    ]);

    Quest::create([
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'title' => 'Find mint',
    ]);

    Quest::create([
        'user_id' => $otherUser->id,
        'title' => 'Other user quest',
    ]);

    $paginator = questRepository()->paginateForUser($user, []);
    $quest = $paginator->items()[0];

    expect($paginator->perPage())->toBe(15)
        ->and($paginator->total())->toBe(2)
        ->and($quest->relationLoaded('owner'))->toBeTrue()
        ->and($quest->sub_quests_count)->toBe(1);
});

it('supports status due before and search filters', function (): void {
    $user = User::factory()->create();

    Quest::create([
        'user_id' => $user->id,
        'title' => 'Slay cave spider',
        'description' => 'Bring antivenom',
        'status' => 'in_progress',
        'due_at' => now()->addDay(),
    ]);

    Quest::create([
        'user_id' => $user->id,
        'title' => 'Collect river stones',
        'description' => 'Spider silk is not needed here',
        'status' => 'open',
        'due_at' => now()->addWeek(),
    ]);

    $results = questRepository()->paginateForUser($user, [
        'status' => 'in_progress',
        'due_before' => now()->addDays(2),
        'search' => 'spider',
    ]);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->title)->toBe('Slay cave spider');
});

it('supports quest sorting options', function (): void {
    $user = User::factory()->create();

    Quest::create([
        'user_id' => $user->id,
        'title' => 'Low XP',
        'xp_reward' => 10,
        'due_at' => now()->addDays(2),
    ]);

    Quest::create([
        'user_id' => $user->id,
        'title' => 'High XP',
        'xp_reward' => 100,
        'due_at' => now()->addDay(),
    ]);

    $xpDesc = questRepository()->paginateForUser($user, ['sort' => 'xp_reward_desc']);
    $xpAsc = questRepository()->paginateForUser($user, ['sort' => 'xp_reward_asc']);
    $dueAsc = questRepository()->paginateForUser($user, ['sort' => 'due_at_asc']);

    expect($xpDesc->items()[0]->title)->toBe('High XP')
        ->and($xpAsc->items()[0]->title)->toBe('Low XP')
        ->and($dueAsc->items()[0]->title)->toBe('High XP');
});

it('loads a single user quest with nested subquests recursively', function (): void {
    $user = User::factory()->create();

    $parent = Quest::create([
        'user_id' => $user->id,
        'title' => 'Main quest',
    ]);

    $child = Quest::create([
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'title' => 'Child quest',
    ]);

    Quest::create([
        'user_id' => $user->id,
        'parent_id' => $child->id,
        'title' => 'Grandchild quest',
    ]);

    $quest = questRepository()->findForUser($user, $parent->id);

    expect($quest)->not->toBeNull()
        ->and($quest->relationLoaded('owner'))->toBeTrue()
        ->and($quest->relationLoaded('subQuests'))->toBeTrue()
        ->and($quest->subQuests)->toHaveCount(1)
        ->and($quest->subQuests->first()->relationLoaded('subQuests'))->toBeTrue()
        ->and($quest->subQuests->first()->subQuests)->toHaveCount(1);
});

it('limits recursive subquest loading depth for single quest lookups', function (): void {
    $user = User::factory()->create();

    $parent = Quest::create([
        'user_id' => $user->id,
        'title' => 'Depth zero',
    ]);

    $child = Quest::create([
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'title' => 'Depth one',
    ]);

    $grandchild = Quest::create([
        'user_id' => $user->id,
        'parent_id' => $child->id,
        'title' => 'Depth two',
    ]);

    Quest::create([
        'user_id' => $user->id,
        'parent_id' => $grandchild->id,
        'title' => 'Depth three',
    ]);

    $quest = questRepository()->findForUser($user, $parent->id, maxDepth: 2);
    $loadedGrandchild = $quest?->subQuests->first()?->subQuests->first();

    expect($quest)->not->toBeNull()
        ->and($quest->subQuests)->toHaveCount(1)
        ->and($quest->subQuests->first()->subQuests)->toHaveCount(1)
        ->and($loadedGrandchild?->relationLoaded('subQuests'))->toBeFalse();
});

it('creates updates completes deletes and returns subquests through the repository', function (): void {
    $user = User::factory()->create();

    $quest = questRepository()->create($user, [
        'title' => 'Explore ruins',
        'xp_reward' => 40,
    ]);

    $updated = questRepository()->update($quest, [
        'title' => 'Explore ancient ruins',
    ]);

    $completed = questRepository()->complete($updated);

    $subQuest = questRepository()->createSubQuest($completed, [
        'title' => 'Translate wall markings',
    ]);

    $subQuests = questRepository()->getSubQuests($completed);

    expect($quest->relationLoaded('owner'))->toBeTrue()
        ->and($updated->title)->toBe('Explore ancient ruins')
        ->and($completed->status)->toBe('completed')
        ->and($subQuest->user_id)->toBe($user->id)
        ->and($subQuests)->toHaveCount(1)
        ->and($subQuests->first()->relationLoaded('owner'))->toBeTrue()
        ->and(questRepository()->delete($completed))->toBeTrue()
        ->and(Quest::count())->toBe(0);
});
