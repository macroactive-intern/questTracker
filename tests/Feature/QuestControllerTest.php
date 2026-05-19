<?php

use App\Events\QuestCompleted;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists authenticated user quests with pagination metadata', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Quest::create([
        'user_id' => $user->id,
        'title' => 'Owned quest',
    ]);

    Quest::create([
        'user_id' => $otherUser->id,
        'title' => 'Hidden quest',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/quests')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Owned quest')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
});

it('stores a quest using the form request and resource response', function (): void {
    $user = User::factory()->create(['name' => 'Hadlee']);

    Sanctum::actingAs($user);

    $this->postJson('/api/quests', [
        'title' => 'New quest',
        'description' => 'A fresh task.',
        'xp_reward' => 250,
        'due_at' => now()->addDay()->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'New quest')
        ->assertJsonPath('data.owner.name', 'Hadlee');

    expect(Quest::where('title', 'New quest')->exists())->toBeTrue();
});

it('shows updates completes and destroys a quest through the service layer', function (): void {
    Event::fake();

    $user = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $user->id,
        'title' => 'Old title',
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/quests/{$quest->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Old title');

    $this->putJson("/api/quests/{$quest->id}", [
        'title' => 'New title',
        'status' => 'in_progress',
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'New title')
        ->assertJsonPath('data.status', 'in_progress');

    $this->patchJson("/api/quests/{$quest->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    Event::assertDispatched(QuestCompleted::class);

    $this->deleteJson("/api/quests/{$quest->id}")
        ->assertNoContent();

    expect(Quest::count())->toBe(0);
});

it('returns 404 when accessing another user quest', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $quest = Quest::create([
        'user_id' => $otherUser->id,
        'title' => 'Private quest',
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/quests/{$quest->id}")->assertNotFound();
    $this->putJson("/api/quests/{$quest->id}", ['title' => 'Nope'])->assertNotFound();
    $this->deleteJson("/api/quests/{$quest->id}")->assertNotFound();
});

it('lists and creates subquests for an owned parent quest', function (): void {
    $user = User::factory()->create();
    $parent = Quest::create([
        'user_id' => $user->id,
        'title' => 'Parent quest',
    ]);

    Quest::create([
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'title' => 'Existing subquest',
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/quests/{$parent->id}/sub-quests")
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Existing subquest');

    $this->postJson("/api/quests/{$parent->id}/sub-quests", [
        'title' => 'New subquest',
        'xp_reward' => 50,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'New subquest');

    expect(Quest::where('parent_id', $parent->id)->count())->toBe(2);
});

it('requires authentication for quest endpoints', function (): void {
    $this->getJson('/api/quests')->assertUnauthorized();
    $this->postJson('/api/quests', ['title' => 'Nope'])->assertUnauthorized();
});
