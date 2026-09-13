<?php

namespace App\Exceptions;

use RuntimeException;

class BookingOverlapException extends RuntimeException
{
    public function __construct(string $message = 'This time slot overlaps an existing confirmed booking.')
    {
        parent::__construct($message);
    }
}
