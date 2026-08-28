<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOperatorRequest;
use App\Http\Resources\OperatorResource;
use App\Services\WalkieTalkieService;
use Illuminate\Http\JsonResponse;

class OperatorController extends Controller
{
    public function store(StoreOperatorRequest $request, WalkieTalkieService $walkieTalkie): JsonResponse
    {
        $registration = $walkieTalkie->register($request->validated('callsign'));

        return response()->json([
            'data' => [
                'operator' => (new OperatorResource($registration['operator']))->resolve(),
                'token' => $registration['token']->plainTextToken,
            ],
        ], 201);
    }
}
