<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Services\WalkieTalkieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(
        RegisterUserRequest $request,
        WalkieTalkieService $walkieTalkie,
    ): JsonResponse {
        $user = $walkieTalkie->createAccount(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('callsign'),
            $request->validated('name'),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return $this->userResponse($request, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->user()->forceFill(['last_seen_at' => now()])->save();

        return $this->userResponse($request);
    }

    public function user(Request $request): JsonResponse
    {
        return $this->userResponse($request);
    }

    public function logout(Request $request, WalkieTalkieService $walkieTalkie): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $walkieTalkie->disconnect($user);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::forgetGuards();

        return response()->noContent();
    }

    private function userResponse(Request $request, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data' => (new AuthenticatedUserResource($request->user()))->resolve($request),
            'meta' => [
                'session_lifetime_minutes' => (int) config('session.lifetime'),
            ],
        ], $status);
    }
}
