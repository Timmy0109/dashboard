<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MemberApprovalController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TaskAttachmentController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\PasswordResetController;

// Public
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register/validate-code', [RegisterController::class, 'validateCode'])->middleware('throttle:20,1');
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:10,1');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');

// Authenticated (Sanctum cookie session)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'avatar']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/todo', [TodoController::class, 'index']);
    Route::get('/stats', [StatsController::class, 'index']);
    Route::get('/stats/member/{userId}', [StatsController::class, 'memberDetail']);

    // User management (admin only)
    Route::apiResource('users', UserController::class)->except(['show']);

    // Settings management (admin only)
    Route::prefix('settings')->group(function () {
        Route::get('categories', [SettingController::class, 'categoriesIndex']);
        Route::post('categories', [SettingController::class, 'categoriesStore']);
        Route::put('categories/{category}', [SettingController::class, 'categoriesUpdate']);
        Route::delete('categories/{category}', [SettingController::class, 'categoriesDestroy']);

        Route::get('priorities', [SettingController::class, 'prioritiesIndex']);
        Route::post('priorities', [SettingController::class, 'prioritiesStore']);
        Route::put('priorities/{priority}', [SettingController::class, 'prioritiesUpdate']);
        Route::delete('priorities/{priority}', [SettingController::class, 'prioritiesDestroy']);

        Route::get('statuses', [SettingController::class, 'statusesIndex']);
        Route::post('statuses', [SettingController::class, 'statusesStore']);
        Route::put('statuses/{status}', [SettingController::class, 'statusesUpdate']);
        Route::delete('statuses/{status}', [SettingController::class, 'statusesDestroy']);
    });

    Route::prefix('lookups')->group(function () {
        Route::get('categories', [LookupController::class, 'categories']);
        Route::get('priorities', [LookupController::class, 'priorities']);
        Route::get('statuses', [LookupController::class, 'statuses']);
        Route::get('users', [LookupController::class, 'users']);
        Route::get('features', [LookupController::class, 'myFeatures']);
    });

    // Admin — company management
    Route::prefix('admin/companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index']);
        Route::post('/', [CompanyController::class, 'store']);
        Route::put('{company}', [CompanyController::class, 'update']);
        Route::delete('{company}', [CompanyController::class, 'destroy']);
        Route::post('{id}/restore', [CompanyController::class, 'restore']);
        Route::get('{company}/features', [CompanyController::class, 'features']);
        Route::put('{company}/features/{key}', [CompanyController::class, 'toggleFeature']);
        Route::post('{company}/invite-code', [CompanyController::class, 'regenerateInviteCode']);
        Route::get('{company}/users', [CompanyController::class, 'users']);
    });

    // Manager — member approval
    Route::prefix('manager/members')->group(function () {
        Route::get('/', [MemberApprovalController::class, 'members']);
        Route::get('pending', [MemberApprovalController::class, 'pending']);
        Route::put('{user}', [MemberApprovalController::class, 'update']);
        Route::delete('{user}', [MemberApprovalController::class, 'destroy']);
        Route::post('{user}/approve', [MemberApprovalController::class, 'approve']);
        Route::post('{user}/reject', [MemberApprovalController::class, 'reject']);
    });

    // Import / Export
    Route::get('projects/export',           [ExportController::class, 'allProjects']);
    Route::get('projects/import/template',  [ImportController::class, 'template']);
    Route::post('projects/import/preview',  [ImportController::class, 'preview']);
    Route::post('projects/import/confirm',  [ImportController::class, 'confirm']);

    Route::apiResource('projects', ProjectController::class);
    Route::prefix('projects/{project}')->group(function () {
        Route::get('export', [ExportController::class, 'project']);
        Route::get('members', [ProjectController::class, 'members']);
        Route::post('members', [ProjectController::class, 'addMember']);
        Route::delete('members/{userId}', [ProjectController::class, 'removeMember']);
        Route::apiResource('tasks', TaskController::class)->except(['index']);
        Route::get('tasks', [TaskController::class, 'index']);
        Route::get('tasks/{task}/activities', [TaskController::class, 'activities']);
        Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index']);
        Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store']);
        Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy']);
        Route::get('tasks/{task}/attachments', [TaskAttachmentController::class, 'index']);
        Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store']);
        Route::delete('tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy']);
    });
});
