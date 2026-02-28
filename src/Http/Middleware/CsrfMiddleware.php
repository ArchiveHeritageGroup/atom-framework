<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Services\CsrfService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF Middleware for standalone (Heratio) mode.
 *
 * Validates CSRF tokens on POST/PUT/DELETE/PATCH requests.
 * Respects enforcement mode: 'log', 'enforce', or 'off'.
 *
 * In dual-stack mode (Symfony), CSRF is enforced via AhgController::boot()
 * instead of this middleware.
 */
class CsrfMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request  $request
     * @param callable $next    The next middleware/handler
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        $method = strtoupper($request->getMethod());

        // Only check mutating methods
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $allowed = CsrfService::enforce();

            if (!$allowed) {
                return new Response(
                    json_encode(['error' => 'CSRF token validation failed']),
                    403,
                    ['Content-Type' => 'application/json']
                );
            }
        }

        return $next($request);
    }
}
