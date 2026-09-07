<?php

namespace App\Tenancy\Exceptions;

use RuntimeException;

class ClosedAcademicYearException extends RuntimeException
{
    public function __construct(string $message = 'Cannot modify records in a closed academic year. Historical academic years and sections are read-only.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
