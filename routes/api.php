<?php

use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::apiResource('teams', TeamController::class);
Route::apiResource('players', PlayerController::class);
