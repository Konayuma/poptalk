<?php

namespace App\Exceptions;

use RuntimeException;

class PttNotHeldException extends RuntimeException
{
    public static function make(): self
    {
        return new self('You are not holding the push-to-talk lock.');
    }
}
