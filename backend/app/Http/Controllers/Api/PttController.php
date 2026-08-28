<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FrequencyResource;
use App\Models\Frequency;
use App\Services\WalkieTalkieService;
use Illuminate\Http\Request;

class PttController extends Controller
{
    public function start(Request $request, Frequency $frequency, WalkieTalkieService $walkieTalkie): FrequencyResource
    {
        return new FrequencyResource($walkieTalkie->startPtt($request->user(), $frequency));
    }

    public function stop(Request $request, Frequency $frequency, WalkieTalkieService $walkieTalkie): FrequencyResource
    {
        return new FrequencyResource($walkieTalkie->stopPtt($request->user(), $frequency));
    }
}
