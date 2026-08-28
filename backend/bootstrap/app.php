<?php

use App\Exceptions\FrequencyBusyException;
use App\Exceptions\NotOnFrequencyException;
use App\Exceptions\PttNotHeldException;
use App\Exceptions\TargetNotOnFrequencyException;
use App\Exceptions\TransmissionNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (FrequencyBusyException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'channel_busy',
                'errors' => [
                    'channel' => ['Wait for the current caller to release PTT.'],
                ],
            ], 409);
        });

        $exceptions->render(function (NotOnFrequencyException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        });

        $exceptions->render(function (PttNotHeldException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        });

        $exceptions->render(function (TargetNotOnFrequencyException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (TransmissionNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'transmission_not_found',
            ], 404);
        });
    })->create();
