<?php

namespace App\Exceptions;

use RuntimeException;

class FrequencyBusyException extends RuntimeException
{
    public static function alreadyTalking(string $callsign): self
    {
        return new self("Frequency is held by {$callsign}.");
    }
}
