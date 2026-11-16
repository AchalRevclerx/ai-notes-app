<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteSearchController;
use App\Http\Controllers\NoteSummaryController;
use App\Models\Note;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Declared before the resource so "search" is never treated as a {note} id.
    Route::get('notes/search', NoteSearchController::class)->middleware('throttle:ai');

    Route::apiResource('notes', NoteController::class)
        ->where(['note' => Note::ROUTE_ID_PATTERN])
        ->middlewareFor(['show', 'update', 'destroy'], 'can:manage,note');

    Route::post('notes/{note}/summary', NoteSummaryController::class)
        ->where('note', Note::ROUTE_ID_PATTERN)
        ->middleware(['can:manage,note', 'throttle:ai']);
});
