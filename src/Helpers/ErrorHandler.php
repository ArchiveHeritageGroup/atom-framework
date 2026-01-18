<?php

namespace AtomFramework\Helpers;

use AtomExtensions\Exceptions\ServiceException;

/**
 * Standardized error handling utilities.
 */
class ErrorHandler
{
    private static bool $debugMode = false;

    /**
     * Set debug mode for detailed error output.
     */
    public static function setDebugMode(bool $enabled): void
    {
        self::$debugMode = $enabled;
    }

    /**
     * Check if debug mode is enabled.
     */
    public static function isDebugMode(): bool
    {
        if (defined('SF_DEBUG')) {
            return SF_DEBUG;
        }
        return self::$debugMode;
    }

    /**
     * Log error and optionally rethrow.
     */
    public static function log(\Throwable $e, string $context = '', bool $rethrow = false): void
    {
        $message = self::formatLogMessage($e, $context);
        error_log($message);

        if ($rethrow) {
            throw $e;
        }
    }

    /**
     * Format error message for logging.
     */
    public static function formatLogMessage(\Throwable $e, string $context = ''): string
    {
        $parts = [];

        if ($context) {
            $parts[] = "[{$context}]";
        }

        $parts[] = get_class($e);
        $parts[] = $e->getMessage();
        $parts[] = "in {$e->getFile()}:{$e->getLine()}";

        return implode(' - ', $parts);
    }

    /**
     * Handle exception and return appropriate response array.
     */
    public static function handle(\Throwable $e, string $context = ''): array
    {
        self::log($e, $context);

        if ($e instanceof ServiceException) {
            return $e->toArray();
        }

        $response = [
            'success' => false,
            'message' => 'An unexpected error occurred.',
            'code' => 500,
        ];

        if (self::isDebugMode()) {
            $response['debug'] = [
                'message' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return $response;
    }

    /**
     * Execute callback with error handling.
     */
    public static function wrap(callable $callback, string $context = '')
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return self::handle($e, $context);
        }
    }

    /**
     * Execute callback with silent error handling (logs but doesn't fail).
     */
    public static function silent(callable $callback, $default = null, string $context = '')
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            self::log($e, $context);
            return $default;
        }
    }

    /**
     * Execute callback with retry logic.
     */
    public static function retry(
        callable $callback,
        int $attempts = 3,
        int $delayMs = 100,
        string $context = ''
    ) {
        $lastException = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;
                self::log($e, "{$context} (attempt {$i}/{$attempts})");

                if ($i < $attempts) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Assert condition or throw ServiceException.
     */
    public static function assert(bool $condition, string $message, int $code = 400): void
    {
        if (!$condition) {
            throw new ServiceException($message, $code, [], $message);
        }
    }

    /**
     * Assert resource exists or throw not found.
     */
    public static function assertFound($resource, string $name, $id = null): void
    {
        if (!$resource) {
            throw ServiceException::notFound($name, $id);
        }
    }

    /**
     * Create error response for Symfony action.
     */
    public static function sfError(\Throwable $e, string $context = ''): string
    {
        return json_encode(self::handle($e, $context));
    }
}
