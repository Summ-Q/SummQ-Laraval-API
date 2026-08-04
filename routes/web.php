<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api')->group(function () {
    Route::get('/tests', function () {
    return response()->json(['message' => 'API is working22!']);
});
});
