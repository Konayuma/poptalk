<?php

namespace App\Exceptions;

use RuntimeException;

class NotOnFrequencyException extends RuntimeException
{
    public static function make(): self
    {
        return new self('You must join this frequency first.');
    }
}
