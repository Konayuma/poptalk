<?php

namespace App\Exceptions;

use RuntimeException;

class TargetNotOnFrequencyException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Target operator is not on this frequency.');
    }
}
