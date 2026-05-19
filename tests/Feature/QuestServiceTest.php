<?php

use App\Events\QuestCompleted;
use App\Models\Quest;
use App\Models\User;
use App\Services\QuestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function questService(): QuestService
{
    return app(QuestService::class);
}

it('creates and returns paginated quests through the repository layer', function (): void {
    $user = User::factory()->create();

    questService()->createQuest($user, [
        'title' => 'Find the lantern',
        'xp_reward' => 15,
    ]);

    $quests = questService()->getPaginatedQuests($user, []);

    expect($quests->total())->toBe(1)
        ->and($quests->items()[0]->title)->toBe('Find the lantern');
});

it('gets a single quest only for its owner', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $quest = Quest::create([
        'user_id' => $owner->id,
        'title' => 'Decode the rune',
    ]);

    expect(questService()->getQuest($owner, $quest->id))->not->toBeNull()
        ->and(questService()->getQuest($otherUser, $quest->id))->toBeNull();
});

it('updates deletes and creates sub quests only for the owner', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $quest = Quest::create([
        'user_id' => $owner->id,
        'title' => 'Chart the marsh',
    ]);

    $updated = questService()->updateQuest($owner, $quest, [
        'title' => 'Chart the misty marsh',
    ]);

    $subQuest = questService()->createSubQuest($owner, $updated, [
        'title' => 'Mark safe crossings',
    ]);

    expect($updated->title)->toBe('Chart the misty marsh')
        ->and($subQuest->parent_id)->toBe($quest->id);

    expect(fn () => questService()->updateQuest($otherUser, $quest, ['title' => 'Nope']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => questService()->createSubQuest($otherUser, $quest, ['title' => 'Nope']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => questService()->deleteQuest($otherUser, $quest))
        ->toThrow(AuthorizationException::class);

    expect(questService()->deleteQuest($owner, $quest))->toBeTrue()
        ->and(Quest::count())->toBe(0);
});

it('dispatches quest completed when completing an open quest', function (): void {
    Event::fake();

    $owner = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $owner->id,
        'title' => 'Return the relic',
    ]);

    $completed = questService()->completeQuest($owner, $quest);

    expect($completed->status)->toBe('completed');

    Event::assertDispatched(QuestCompleted::class, fn (QuestCompleted $event) => $event->quest->is($completed));
});

it('does not dispatch quest completed when quest is already completed', function (): void {
    Event::fake();

    $owner = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $owner->id,
        'title' => 'Already done',
        'status' => 'completed',
    ]);

    $completed = questService()->completeQuest($owner, $quest);

    expect($completed->status)->toBe('completed');

    Event::assertNotDispatched(QuestCompleted::class);
});

it('does not complete a quest owned by another user', function (): void {
    Event::fake();

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $owner->id,
        'title' => 'Guarded quest',
    ]);

    expect(fn () => questService()->completeQuest($otherUser, $quest))
        ->toThrow(AuthorizationException::class);

    Event::assertNotDispatched(QuestCompleted::class);
});
