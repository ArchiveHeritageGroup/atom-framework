<?php

declare(strict_types=1);

namespace AtomExtensions\Exceptions;

/**
 * Base API Exception - Replaces QubitApiException.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ApiException extends \Exception
{
    protected int $statusCode = 500;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
