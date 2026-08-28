<?php

namespace App\Exceptions;

use RuntimeException;

class TransmissionNotFoundException extends RuntimeException
{
    public static function make(): self
    {
        return new self('The transmission is no longer active.');
    }
}
