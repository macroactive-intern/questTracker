<?php

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

it('validates register request payloads', function (): void {
    $request = RegisterRequest::create('/api/register', 'POST');

    expect($request->authorize())->toBeTrue();

    $valid = Validator::make([
        'name' => 'Hadlee',
        'email' => 'hadlee@example.com',
        'password' => 'password123',
        'device_name' => 'pest',
    ], $request->rules());

    $invalid = Validator::make([
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
    ], $request->rules());

    expect($valid->passes())->toBeTrue()
        ->and($invalid->fails())->toBeTrue()
        ->and($invalid->errors()->has('name'))->toBeTrue()
        ->and($invalid->errors()->has('email'))->toBeTrue()
        ->and($invalid->errors()->has('password'))->toBeTrue();
});

it('requires unique emails for registration', function (): void {
    User::factory()->create(['email' => 'hadlee@example.com']);

    $request = RegisterRequest::create('/api/register', 'POST');

    $validator = Validator::make([
        'name' => 'Hadlee',
        'email' => 'hadlee@example.com',
        'password' => 'password123',
    ], $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue();
});

it('validates login request payloads', function (): void {
    $request = LoginRequest::create('/api/login', 'POST');

    expect($request->authorize())->toBeTrue();

    $valid = Validator::make([
        'email' => 'hadlee@example.com',
        'password' => 'password123',
        'device_name' => 'pest',
    ], $request->rules());

    $invalid = Validator::make([
        'email' => 'not-an-email',
        'password' => '',
    ], $request->rules());

    expect($valid->passes())->toBeTrue()
        ->and($invalid->fails())->toBeTrue()
        ->and($invalid->errors()->has('email'))->toBeTrue()
        ->and($invalid->errors()->has('password'))->toBeTrue();
});
