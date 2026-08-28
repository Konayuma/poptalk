<?php

namespace App\Broadcasting;

use App\Models\User;

class OperatorChannel
{
    public function join(User $user, string $uuid): bool
    {
        return ! $user->isStale() && hash_equals($user->uuid, $uuid);
    }
}
