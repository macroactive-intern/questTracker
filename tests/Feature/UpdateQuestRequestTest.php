<?php

use App\Http\Requests\UpdateQuestRequest;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function validateUpdateQuestPayload(User $user, array $payload)
{
    $request = UpdateQuestRequest::create('/quests/1', 'PATCH', $payload);
    $request->setUserResolver(fn () => $user);

    return Validator::make($payload, $request->rules());
}

it('authorizes authenticated users only', function (): void {
    $request = UpdateQuestRequest::create('/quests/1', 'PATCH');

    expect($request->authorize())->toBeFalse();

    $request->setUserResolver(fn () => User::factory()->make());

    expect($request->authorize())->toBeTrue();
});

it('allows empty payloads for partial updates', function (): void {
    $user = User::factory()->create();

    $validator = validateUpdateQuestPayload($user, []);

    expect($validator->passes())->toBeTrue();
});

it('validates provided partial update fields', function (): void {
    $user = User::factory()->create();

    $valid = validateUpdateQuestPayload($user, [
        'status' => 'completed',
        'xp_reward' => 500,
    ]);

    $invalid = validateUpdateQuestPayload($user, [
        'title' => '',
        'status' => 'unknown',
        'xp_reward' => 10001,
    ]);

    expect($valid->passes())->toBeTrue()
        ->and($invalid->fails())->toBeTrue()
        ->and($invalid->errors()->has('title'))->toBeTrue()
        ->and($invalid->errors()->has('status'))->toBeTrue()
        ->and($invalid->errors()->has('xp_reward'))->toBeTrue();
});

it('requires provided parent quest to belong to the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUsersQuest = Quest::create([
        'user_id' => $otherUser->id,
        'title' => 'Foreign parent quest',
    ]);

    $validator = validateUpdateQuestPayload($user, [
        'parent_id' => $otherUsersQuest->id,
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('parent_id'))->toBeTrue();
});
