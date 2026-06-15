<?php

use App\Http\Controllers\Api\V1\DteEmailNotificationController;
use App\Http\Controllers\Api\V1\NotificationMailTransportController;
use App\Http\Controllers\Api\V1\NotificationSenderAliasController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'service' => config('app.name', 'Stelfaro Notifications'),
        'timestamp' => now()->toISOString(),
    ]));

    Route::middleware('internal.token')->group(function (): void {
        Route::get('sender-aliases', [NotificationSenderAliasController::class, 'index']);
        Route::post('sender-aliases', [NotificationSenderAliasController::class, 'store']);
        Route::patch('sender-aliases/{alias}', [NotificationSenderAliasController::class, 'update']);
        Route::get('mail-transport', [NotificationMailTransportController::class, 'show']);
        Route::post('mail-transport', [NotificationMailTransportController::class, 'store']);
        Route::post('dte/{document}/email', DteEmailNotificationController::class);
    });
});
