<?php

namespace App\Broadcasting;

use App\Models\Frequency;
use App\Models\User;

class FrequencyChannel
{
    /**
     * @return array{id: string, callsign: string}|false
     */
    public function join(User $user, mixed $number): array|false
    {
        $frequency = Frequency::query()->where('number', (int) $number)->first();

        if ($frequency === null) {
            return false;
        }

        $user->unsetRelation('membership');
        $user->load('membership');

        return $user->isOnFrequency($frequency)
            && ! $user->membership->isStale()
            ? $user->presencePayload()
            : false;
    }
}
