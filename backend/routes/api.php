<?php

use App\Http\Controllers\Api\FrequencyController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\PttController;
use App\Http\Controllers\Api\SignalController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TransmissionController;
use App\Http\Middleware\EnsureRadioSessionIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'data' => [
                'service' => 'poptalk',
                'status' => 'ok',
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    });
    Route::post('/sessions', [SessionController::class, 'store'])
        ->middleware('throttle:operators');

    Route::middleware(['auth:sanctum', EnsureRadioSessionIsActive::class])
        ->group(function (): void {
            Route::get('/sessions/current', [SessionController::class, 'show']);
            Route::patch('/sessions/current', [SessionController::class, 'update']);
            Route::delete('/sessions/current', [SessionController::class, 'destroy']);
            Route::post('/sessions/current/heartbeat', [SessionController::class, 'heartbeat']);

            Route::get('/channels', [ChannelController::class, 'index']);
            Route::get('/channels/{frequency}', [ChannelController::class, 'show']);
            Route::post('/channels/{frequency}/transmissions', [TransmissionController::class, 'store']);

            Route::patch('/transmissions/{transmission}', [TransmissionController::class, 'update']);
            Route::delete('/transmissions/{transmission}', [TransmissionController::class, 'destroy']);
        });
});

Route::post('/operators', [OperatorController::class, 'store'])
    ->middleware('throttle:operators');

Route::middleware(['auth:sanctum', EnsureRadioSessionIsActive::class])->group(function (): void {
    Route::get('/me', [MeController::class, 'show']);
    Route::post('/me/heartbeat', [MeController::class, 'heartbeat']);

    Route::get('/frequencies', [FrequencyController::class, 'index']);
    Route::get('/frequencies/{frequency}', [FrequencyController::class, 'show']);
    Route::post('/frequencies/{frequency}/join', [FrequencyController::class, 'join']);
    Route::post('/frequencies/{frequency}/leave', [FrequencyController::class, 'leave']);

    Route::post('/frequencies/{frequency}/ptt/start', [PttController::class, 'start']);
    Route::post('/frequencies/{frequency}/ptt/stop', [PttController::class, 'stop']);

    Route::get('/frequencies/{frequency}/signals', [SignalController::class, 'index']);
    Route::post('/frequencies/{frequency}/signals', [SignalController::class, 'store'])
        ->middleware('throttle:signals');
});
