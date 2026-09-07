<?php

namespace App\Tenancy\Exceptions;

use RuntimeException;

class TenantNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'School tenant could not be resolved from the provided identifier.', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
