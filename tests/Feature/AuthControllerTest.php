<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('registers a user and returns a token with user resource', function (): void {
    $response = $this->postJson('/api/register', [
        'name' => 'Hadlee',
        'email' => 'hadlee@example.com',
        'password' => 'password123',
        'device_name' => 'pest',
    ]);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name'],
        ])
        ->assertJsonPath('user.name', 'Hadlee')
        ->assertJsonMissingPath('user.email');

    expect(PersonalAccessToken::count())->toBe(1)
        ->and(PersonalAccessToken::first()->abilities)->toBe(['score:submit'])
        ->and(User::where('email', 'hadlee@example.com')->exists())->toBeTrue();
});

it('logs in with hashed password checks and returns a token', function (): void {
    User::factory()->create([
        'name' => 'Hadlee',
        'email' => 'hadlee@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'hadlee@example.com',
        'password' => 'password123',
        'device_name' => 'pest',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name'],
        ])
        ->assertJsonPath('user.name', 'Hadlee');

    expect(PersonalAccessToken::count())->toBe(1)
        ->and(PersonalAccessToken::first()->abilities)->toBe(['score:submit']);
});

it('rejects invalid login credentials', function (): void {
    User::factory()->create([
        'email' => 'hadlee@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/api/login', [
        'email' => 'hadlee@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::count())->toBe(0);
});

it('revokes the current token on logout', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('pest')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertNoContent();

    expect(PersonalAccessToken::count())->toBe(0);
});
