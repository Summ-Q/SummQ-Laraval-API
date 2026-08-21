<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\StudyController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-attempts');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-attempts');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/decks/{deck}/cards', [FlashcardController::class, 'index'])
        ->missing(function () {
            return response()->json(['message' => 'Record not found.'], 404);
        });

    Route::post('/decks/{deck}/generate', [FlashcardController::class, 'generate'])
        ->missing(function () {
            return response()->json(['message' => 'Record not found.'], 404);
        })->middleware('throttle:ai-generation');

    Route::get('/decks', [DeckController::class, 'index']);
    Route::post('/decks', [DeckController::class, 'store']);
    Route::delete('/decks/{deck}', [DeckController::class, 'destroy']);

    Route::get('/decks/{deck}/study', [StudyController::class, 'index']);
    Route::post('/reviews/{flashcard}', [StudyController::class, 'logReview'])->middleware('throttle:study-activity');

    Route::get('/study/performance', [StudyController::class, 'getStudyPerformance']);
});
