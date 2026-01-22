<?php

namespace AtomFramework\Helpers;

/**
 * Helper for standardized API/AJAX responses.
 */
class ResponseHelper
{
    /**
     * Return a success JSON response.
     */
    public static function success($data = null, string $message = 'Success'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Return an error JSON response.
     */
    public static function error(string $message, int $code = 400, $errors = null): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
        ];
    }

    /**
     * Return a paginated response.
     */
    public static function paginated(
        iterable $items,
        int $total,
        int $page,
        int $perPage,
        ?string $message = null
    ): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'items' => $items instanceof \Traversable ? iterator_to_array($items) : $items,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $perPage > 0 ? ceil($total / $perPage) : 0,
                    'has_more' => ($page * $perPage) < $total,
                ],
            ],
        ];
    }

    /**
     * Return a validation error response.
     */
    public static function validationError(array $errors): array
    {
        return self::error('Validation failed', 422, $errors);
    }

    /**
     * Return a not found response.
     */
    public static function notFound(string $resource = 'Resource'): array
    {
        return self::error("{$resource} not found", 404);
    }

    /**
     * Return an unauthorized response.
     */
    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return self::error($message, 401);
    }

    /**
     * Return a forbidden response.
     */
    public static function forbidden(string $message = 'Forbidden'): array
    {
        return self::error($message, 403);
    }

    /**
     * Output JSON response and exit (for standalone scripts).
     */
    public static function outputJson($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Build Symfony action result for JSON response.
     */
    public static function toSymfonyResult(array $response): string
    {
        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
