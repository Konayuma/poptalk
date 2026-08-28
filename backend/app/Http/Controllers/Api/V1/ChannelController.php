<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelStatusResource;
use App\Models\Frequency;
use App\Services\WalkieTalkieService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChannelController extends Controller
{
    public function index(WalkieTalkieService $walkieTalkie): AnonymousResourceCollection
    {
        return ChannelStatusResource::collection($walkieTalkie->listFrequencies());
    }

    public function show(
        Frequency $frequency,
        WalkieTalkieService $walkieTalkie,
    ): ChannelStatusResource {
        return new ChannelStatusResource($walkieTalkie->show($frequency));
    }
}
