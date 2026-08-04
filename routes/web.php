<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('my-api')->group(function () {
    Route::get('/tests', function () {
    return response()->json(['message' => 'API is working22!']);
});
});
