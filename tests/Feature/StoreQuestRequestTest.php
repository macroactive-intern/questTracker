<?php

use App\Http\Requests\StoreQuestRequest;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function validateStoreQuestPayload(User $user, array $payload)
{
    $request = StoreQuestRequest::create('/quests', 'POST', $payload);
    $request->setUserResolver(fn () => $user);

    return Validator::make($payload, $request->rules());
}

it('authorizes authenticated users only', function (): void {
    $request = StoreQuestRequest::create('/quests', 'POST');

    expect($request->authorize())->toBeFalse();

    $request->setUserResolver(fn () => User::factory()->make());

    expect($request->authorize())->toBeTrue();
});

it('validates a store quest payload', function (): void {
    $user = User::factory()->create();
    $parent = Quest::create([
        'user_id' => $user->id,
        'title' => 'Parent quest',
    ]);

    $validator = validateStoreQuestPayload($user, [
        'title' => 'Track the comet',
        'description' => 'Observe the night sky.',
        'status' => 'open',
        'xp_reward' => 250,
        'due_at' => now()->addDay()->toDateTimeString(),
        'parent_id' => $parent->id,
    ]);

    expect($validator->passes())->toBeTrue();
});

it('requires parent quest to belong to the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUsersQuest = Quest::create([
        'user_id' => $otherUser->id,
        'title' => 'Not your parent',
    ]);

    $validator = validateStoreQuestPayload($user, [
        'title' => 'Invalid sub quest',
        'parent_id' => $otherUsersQuest->id,
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('parent_id'))->toBeTrue();
});
