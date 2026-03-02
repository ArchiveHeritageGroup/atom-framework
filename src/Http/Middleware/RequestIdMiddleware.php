<?php

namespace AtomFramework\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Request ID middleware.
 *
 * Assigns a unique request ID to every HTTP request for correlation
 * in error alerts and logs. Accepts an incoming X-Request-Id header
 * from upstream proxies or generates one via random bytes.
 *
 * The ID is stored in:
 *   - Static property (accessible without DI from error handlers)
 *   - sfConfig::set('app_request_id', ...) for Symfony-side access
 *   - X-Request-Id response header for client-side correlation
 */
class RequestIdMiddleware
{
    /** @var string|null Current request ID (static for error handler access) */
    public static ?string $requestId = null;

    public function handle(Request $request, Closure $next)
    {
        // Accept incoming header or generate a new one
        $requestId = $request->header('X-Request-Id');

        if (empty($requestId) || strlen($requestId) > 128) {
            $requestId = bin2hex(random_bytes(16));
        }

        self::$requestId = $requestId;

        // Store in sfConfig for Symfony-side access (error pages, templates)
        if (class_exists('sfConfig', false)) {
            \sfConfig::set('app_request_id', $requestId);
        }

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
