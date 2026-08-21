<?php

use App\Http\Controllers\MessageOpenTrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/t/{token}.png', [MessageOpenTrackingController::class, 'pixel'])
    ->where('token', '[A-Za-z0-9_-]+\.[a-f0-9]{64}')
    ->middleware('throttle:120,1');
