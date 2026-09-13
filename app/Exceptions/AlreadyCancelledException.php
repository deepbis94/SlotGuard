<?php

namespace App\Exceptions;

use RuntimeException;

class AlreadyCancelledException extends RuntimeException
{
    public function __construct(string $message = 'This booking is already cancelled.')
    {
        parent::__construct($message);
    }
}
