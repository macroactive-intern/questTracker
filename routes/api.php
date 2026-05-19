<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\QuestController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function (): void {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::controller(QuestController::class)
        ->prefix('quests')
        ->whereNumber('id')
        ->group(function (): void {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
            Route::patch('/{id}/complete', 'complete');
            Route::get('/{id}/sub-quests', 'subQuests');
            Route::post('/{id}/sub-quests', 'storeSubQuest');
        });
});
