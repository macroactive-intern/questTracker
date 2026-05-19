<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\QuestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/quests', [QuestController::class, 'index']);
    Route::post('/quests', [QuestController::class, 'store']);
    Route::get('/quests/{quest}', [QuestController::class, 'show'])->whereNumber('quest');
    Route::patch('/quests/{quest}', [QuestController::class, 'update'])->whereNumber('quest');
    Route::delete('/quests/{quest}', [QuestController::class, 'destroy'])->whereNumber('quest');
    Route::post('/quests/{quest}/complete', [QuestController::class, 'complete'])->whereNumber('quest');
    Route::get('/quests/{quest}/sub-quests', [QuestController::class, 'subQuests'])->whereNumber('quest');
    Route::post('/quests/{quest}/sub-quests', [QuestController::class, 'storeSubQuest'])->whereNumber('quest');
});
