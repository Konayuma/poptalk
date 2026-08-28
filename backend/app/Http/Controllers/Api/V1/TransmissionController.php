<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransmissionResource;
use App\Models\Frequency;
use App\Services\WalkieTalkieService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TransmissionController extends Controller
{
    public function store(
        Request $request,
        Frequency $frequency,
        WalkieTalkieService $walkieTalkie,
    ): TransmissionResource {
        return new TransmissionResource(
            $walkieTalkie->startPtt($request->user(), $frequency)
        );
    }

    public function update(
        Request $request,
        string $transmission,
        WalkieTalkieService $walkieTalkie,
    ): TransmissionResource {
        return new TransmissionResource(
            $walkieTalkie->heartbeatTransmission($request->user(), $transmission)
        );
    }

    public function destroy(
        Request $request,
        string $transmission,
        WalkieTalkieService $walkieTalkie,
    ): Response {
        $walkieTalkie->stopTransmission($request->user(), $transmission);

        return response()->noContent();
    }
}
