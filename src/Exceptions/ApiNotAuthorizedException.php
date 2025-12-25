<?php

declare(strict_types=1);

namespace AtomExtensions\Exceptions;

/**
 * API Not Authorized Exception - Replaces QubitApiNotAuthorizedException.
 */
class ApiNotAuthorizedException extends ApiException
{
    protected int $statusCode = 401;

    public function __construct(string $message = 'Not authorized')
    {
        parent::__construct($message, 401);
    }
}
