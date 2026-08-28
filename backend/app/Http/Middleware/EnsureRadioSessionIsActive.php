<?php

namespace App\Http\Middleware;

use App\Services\WalkieTalkieService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRadioSessionIsActive
{
    public function __construct(private readonly WalkieTalkieService $walkieTalkie) {}

    public function handle(Request $request, Closure $next): Response
    {
        $operator = $request->user();

        if ($operator === null || $operator->isStale()) {
            if ($operator !== null) {
                $this->walkieTalkie->disconnect($operator);
            }

            return new JsonResponse([
                'message' => 'The radio session has expired.',
                'code' => 'invalid_session_token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
