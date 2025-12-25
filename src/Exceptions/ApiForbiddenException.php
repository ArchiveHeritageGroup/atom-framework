<?php

declare(strict_types=1);

namespace AtomExtensions\Exceptions;

/**
 * API Forbidden Exception - Replaces QubitApiForbiddenException.
 */
class ApiForbiddenException extends ApiException
{
    protected int $statusCode = 403;

    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403);
    }
}
