<?php

namespace App\Exceptions;

use Exception;

class TimetableConflictException extends Exception
{
    public function __construct(
        string $message,
        protected array $details = []
    ) {
        parent::__construct($message, 422);
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
