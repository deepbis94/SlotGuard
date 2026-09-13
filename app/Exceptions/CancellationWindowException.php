<?php

namespace App\Exceptions;

use RuntimeException;

class CancellationWindowException extends RuntimeException
{
    public function __construct(string $message = 'Bookings cannot be cancelled within 24 hours of the start time.')
    {
        parent::__construct($message);
    }
}
