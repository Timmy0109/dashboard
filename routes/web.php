<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Sanctum SPA auth for private broadcast channels
Broadcast::routes(['middleware' => ['auth:sanctum']]);
