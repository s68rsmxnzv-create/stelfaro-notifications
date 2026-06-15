<?php

use App\Http\Controllers\Api\V1\DteEmailNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'service' => config('app.name', 'Stelfaro Notifications'),
        'timestamp' => now()->toISOString(),
    ]));

    Route::middleware('internal.token')->group(function (): void {
        Route::post('dte/{document}/email', DteEmailNotificationController::class);
    });
});
