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

        if ($operator === null) {
            return $this->expiredResponse();
        }

        $operator->unsetRelation('membership');
        $operator->load('membership.frequency');

        if ($operator->isStale() || $operator->membership === null) {
            if ($operator->membership !== null) {
                $this->walkieTalkie->disconnect($operator);
            }

            return $this->expiredResponse();
        }

        return $next($request);
    }

    private function expiredResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'The radio session has expired.',
            'code' => 'radio_session_expired',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
