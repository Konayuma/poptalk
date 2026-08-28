<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSessionRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Http\Resources\RadioSessionResource;
use App\Models\Frequency;
use App\Services\WalkieTalkieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    public function store(
        CreateSessionRequest $request,
        WalkieTalkieService $walkieTalkie,
    ): JsonResponse {
        $registration = $walkieTalkie->register($request->validated('callsign'));
        $frequency = Frequency::query()
            ->where('number', $request->integer('channel'))
            ->firstOrFail();
        $walkieTalkie->join($registration['operator'], $frequency);
        $operator = $registration['operator']->fresh(['membership.frequency']);

        return response()->json([
            'data' => (new RadioSessionResource($operator))->resolve($request),
            'meta' => [
                'session_token' => $registration['token']->plainTextToken,
                'heartbeat_interval_seconds' => (int) config('poptalk.heartbeat_interval_seconds'),
                'presence_ttl_seconds' => (int) config('poptalk.presence_ttl_seconds'),
                'server_time' => now()->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request): RadioSessionResource
    {
        return new RadioSessionResource(
            $request->user()->load('membership.frequency')
        );
    }

    public function update(
        UpdateSessionRequest $request,
        WalkieTalkieService $walkieTalkie,
    ): RadioSessionResource {
        $frequency = $request->has('channel')
            ? Frequency::query()->where('number', $request->integer('channel'))->firstOrFail()
            : null;
        $operator = $walkieTalkie->updateSession(
            $request->user(),
            $request->has('callsign') ? $request->validated('callsign') : null,
            $frequency,
        );

        return new RadioSessionResource($operator);
    }

    public function heartbeat(
        Request $request,
        WalkieTalkieService $walkieTalkie,
    ): RadioSessionResource {
        $walkieTalkie->heartbeat($request->user());

        return new RadioSessionResource(
            $request->user()->fresh(['membership.frequency'])
        );
    }

    public function destroy(
        Request $request,
        WalkieTalkieService $walkieTalkie,
    ): Response {
        $walkieTalkie->disconnect($request->user());

        return response()->noContent();
    }
}
