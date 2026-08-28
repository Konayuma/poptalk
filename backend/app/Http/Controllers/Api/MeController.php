<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FrequencyResource;
use App\Http\Resources\OperatorResource;
use App\Services\WalkieTalkieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request, WalkieTalkieService $walkieTalkie): JsonResponse
    {
        $frequency = $walkieTalkie->heartbeat($request->user());

        return response()->json([
            'data' => [
                'operator' => (new OperatorResource($request->user()))->resolve(),
                'frequency' => $frequency ? (new FrequencyResource($frequency))->resolve() : null,
            ],
        ]);
    }

    public function heartbeat(Request $request, WalkieTalkieService $walkieTalkie): JsonResponse
    {
        $frequency = $walkieTalkie->heartbeat($request->user());

        return response()->json([
            'data' => [
                'ok' => true,
                'frequency' => $frequency ? (new FrequencyResource($frequency))->resolve() : null,
            ],
        ]);
    }
}
