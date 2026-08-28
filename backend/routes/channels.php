<?php

use App\Broadcasting\FrequencyChannel;
use App\Broadcasting\OperatorChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('frequency.{number}', FrequencyChannel::class);
Broadcast::channel('operator.{uuid}', OperatorChannel::class);
