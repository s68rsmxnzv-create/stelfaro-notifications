<?php

use App\Http\Controllers\Api\V1\DteEmailNotificationController;
use App\Http\Controllers\Api\V1\MhFiscalEventEmailNotificationController;
use App\Http\Controllers\Api\V1\NotificationActivityController;
use App\Http\Controllers\Api\V1\NotificationMailTransportController;
use App\Http\Controllers\Api\V1\NotificationMessageController;
use App\Http\Controllers\Api\V1\NotificationSenderAliasController;
use App\Http\Controllers\Api\V1\PlatformInvitationEmailNotificationController;
use App\Http\Controllers\Api\V1\PlatformTemporaryPasswordEmailNotificationController;
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
        Route::get('activities', [NotificationActivityController::class, 'index']);
        Route::post('activities', [NotificationActivityController::class, 'store']);
        Route::post('activities/{activity}/actions', [NotificationActivityController::class, 'storeAction']);
        Route::patch('actions/{action}', [NotificationActivityController::class, 'updateAction']);
        Route::get('messages/{message}', [NotificationMessageController::class, 'show']);

        Route::middleware('internal.client:platform-api')->group(function (): void {
            Route::get('mail-transport', [NotificationMailTransportController::class, 'show']);
            Route::post('mail-transport', [NotificationMailTransportController::class, 'store']);
            Route::post('platform/invitations/email', PlatformInvitationEmailNotificationController::class);
            Route::post('platform/temporary-passwords/email', PlatformTemporaryPasswordEmailNotificationController::class);
        });

        Route::middleware('internal.client:dte-core')->group(function (): void {
            Route::post('dte/{document}/email', DteEmailNotificationController::class);
            Route::post('mh-events/{event}/email', MhFiscalEventEmailNotificationController::class);
        });
    });
});
