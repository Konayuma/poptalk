<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FrequencyResource;
use App\Models\Frequency;
use App\Services\WalkieTalkieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FrequencyController extends Controller
{
    public function index(WalkieTalkieService $walkieTalkie): AnonymousResourceCollection
    {
        return FrequencyResource::collection($walkieTalkie->listFrequencies());
    }

    public function show(Frequency $frequency, WalkieTalkieService $walkieTalkie): FrequencyResource
    {
        return new FrequencyResource($walkieTalkie->show($frequency));
    }

    public function join(Request $request, Frequency $frequency, WalkieTalkieService $walkieTalkie): JsonResponse
    {
        $frequency = $walkieTalkie->join($request->user(), $frequency);

        return (new FrequencyResource($frequency))
            ->response()
            ->setStatusCode(200);
    }

    public function leave(Request $request, Frequency $frequency, WalkieTalkieService $walkieTalkie): JsonResponse
    {
        $walkieTalkie->leave($request->user(), $frequency);

        return response()->json([
            'data' => [
                'ok' => true,
            ],
        ]);
    }
}
