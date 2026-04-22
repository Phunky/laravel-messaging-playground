<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Phunky\Http\Controllers\Api\ApiTokenController;

Route::post('/auth/token', [ApiTokenController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/auth/logout', [ApiTokenController::class, 'destroy'])
    ->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
