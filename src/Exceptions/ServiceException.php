<?php

declare(strict_types=1);

namespace AtomExtensions\Exceptions;

/**
 * Service Layer Exception for business logic errors.
 * Use this for recoverable errors in service classes.
 */
class ServiceException extends \Exception
{
    protected int $statusCode = 500;
    protected array $context = [];
    protected ?string $userMessage = null;

    public function __construct(
        string $message = '',
        int $statusCode = 500,
        array $context = [],
        ?string $userMessage = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->context = $context;
        $this->userMessage = $userMessage;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get user-friendly message (safe to display).
     */
    public function getUserMessage(): string
    {
        return $this->userMessage ?? 'An error occurred. Please try again.';
    }

    /**
     * Convert to array for JSON response.
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'message' => $this->getUserMessage(),
            'code' => $this->statusCode,
        ];
    }

    // Factory methods for common error types

    public static function notFound(string $resource, $id = null): self
    {
        $message = $id ? "{$resource} with ID {$id} not found" : "{$resource} not found";
        return new self($message, 404, ['resource' => $resource, 'id' => $id], $message);
    }

    public static function validationFailed(array $errors): self
    {
        return new self(
            'Validation failed: ' . json_encode($errors),
            422,
            ['errors' => $errors],
            'Please check your input and try again.'
        );
    }

    public static function unauthorized(string $reason = 'Authentication required'): self
    {
        return new self($reason, 401, [], $reason);
    }

    public static function forbidden(string $reason = 'Permission denied'): self
    {
        return new self($reason, 403, [], $reason);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409, [], $message);
    }

    public static function internal(string $message, ?\Throwable $previous = null): self
    {
        return new self(
            $message,
            500,
            ['trace' => $previous?->getTraceAsString()],
            'An internal error occurred. Please try again later.',
            $previous
        );
    }

    public static function badRequest(string $message): self
    {
        return new self($message, 400, [], $message);
    }
}
