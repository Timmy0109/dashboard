<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TodoController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/login', [AuthController::class, 'login']);

// Authenticated (Sanctum cookie session)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/todo', [TodoController::class, 'index']);

    Route::prefix('lookups')->group(function () {
        Route::get('categories', [LookupController::class, 'categories']);
        Route::get('priorities', [LookupController::class, 'priorities']);
        Route::get('statuses', [LookupController::class, 'statuses']);
        Route::get('users', [LookupController::class, 'users']);
    });

    Route::apiResource('projects', ProjectController::class);
    Route::prefix('projects/{project}')->group(function () {
        Route::get('members', [ProjectController::class, 'members']);
        Route::post('members', [ProjectController::class, 'addMember']);
        Route::delete('members/{userId}', [ProjectController::class, 'removeMember']);
        Route::apiResource('tasks', TaskController::class)->except(['index'])->shallow();
        Route::get('tasks', [TaskController::class, 'index']);
    });
});
