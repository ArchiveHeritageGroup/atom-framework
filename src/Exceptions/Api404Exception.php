<?php

declare(strict_types=1);

namespace AtomExtensions\Exceptions;

/**
 * API 404 Not Found Exception - Replaces QubitApi404Exception.
 */
class Api404Exception extends ApiException
{
    protected int $statusCode = 404;

    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct($message, 404);
    }
}
