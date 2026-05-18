<?php

use App\Http\Controllers\Api\TaskAttachmentController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Sanctum SPA auth for private broadcast channels
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// Signed URL for file download/preview (no session auth required, signature is the gate)
Route::get('/attachments/{attachment}', [TaskAttachmentController::class, 'download'])
    ->name('attachments.download');
