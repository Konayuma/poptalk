<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RelaySignalRequest;
use App\Http\Resources\SignalResource;
use App\Models\Frequency;
use App\Services\WalkieTalkieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SignalController extends Controller
{
    public function index(Request $request, Frequency $frequency, WalkieTalkieService $walkieTalkie): AnonymousResourceCollection
    {
        $after = max(0, (int) $request->query('after', 0));

        return SignalResource::collection(
            $walkieTalkie->signalsSince($request->user(), $frequency, $after)
        );
    }

    public function store(
        RelaySignalRequest $request,
        Frequency $frequency,
        WalkieTalkieService $walkieTalkie,
    ): JsonResponse {
        $target = $request->filled('target_id')
            ? $walkieTalkie->findOperatorByUuid($request->validated('target_id'))
            : null;

        $signal = $walkieTalkie->relaySignal(
            $request->user(),
            $frequency,
            $request->validated('type'),
            $request->validated('payload'),
            $target,
        );

        return (new SignalResource($signal))
            ->response()
            ->setStatusCode(201);
    }
}
