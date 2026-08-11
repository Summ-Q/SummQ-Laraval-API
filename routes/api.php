<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlashcardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/decks/{deck}/cards', [FlashcardController::class, 'index'])
        ->missing(function () {
            return response()->json(['message' => 'Record not found.'], 404);
        });

    Route::post('/decks/{deck}/generate', [FlashcardController::class, 'generate'])
        ->missing(function () {
            return response()->json(['message' => 'Record not found.'], 404);
        });
});
