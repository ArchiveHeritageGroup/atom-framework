<?php

declare(strict_types=1);

namespace AtomExtensions\Exceptions;

/**
 * API Bad Request Exception - Replaces QubitApiBadRequestException.
 */
class ApiBadRequestException extends ApiException
{
    protected int $statusCode = 400;

    public function __construct(string $message = 'Bad request')
    {
        parent::__construct($message, 400);
    }
}
