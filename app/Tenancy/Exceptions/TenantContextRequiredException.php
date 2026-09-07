<?php

namespace App\Tenancy\Exceptions;

use RuntimeException;

class TenantContextRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Cannot perform tenant-scoped query or operation without an active tenant context.', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
