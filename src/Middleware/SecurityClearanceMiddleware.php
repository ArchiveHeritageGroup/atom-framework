<?php

declare(strict_types=1);

namespace TheAHG\Archive\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use TheAHG\Archive\Services\SecurityClearanceService;

/**
 * Security Clearance Middleware.
 *
 * Checks user clearance level against requested resource
 * Mimics AtoM 2.10 security filtering
 */
class SecurityClearanceMiddleware implements MiddlewareInterface
{
    /**
     * Process request and check security clearance.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Get user from session/auth
        $user = $request->getAttribute('user');

        if (!$user) {
            // No user - treat as public access
            $request = $request->withAttribute('security_level', 0);
            $request = $request->withAttribute('is_admin', false);

            return $handler->handle($request);
        }

        $userId = $user['id'] ?? 0;
        $isAdmin = ($user['is_admin'] ?? false) || ($user['group_id'] ?? 0) === 100;

        // Get user's clearance level
        $clearanceLevel = SecurityClearanceService::getUserClearanceLevel($userId);

        // Add security attributes to request
        $request = $request->withAttribute('security_level', $clearanceLevel);
        $request = $request->withAttribute('is_admin', $isAdmin);
        $request = $request->withAttribute('accessible_classifications',
            SecurityClearanceService::getAccessibleClassificationIds($userId, $isAdmin)
        );

        // Check if accessing specific object
        $objectId = $request->getAttribute('id') ?? $request->getQueryParams()['id'] ?? null;

        if ($objectId && is_numeric($objectId)) {
            $canAccess = SecurityClearanceService::canUserAccessObject($userId, (int) $objectId, $isAdmin);

            if (!$canAccess) {
                // Log denied access
                SecurityClearanceService::logAccessAttempt(
                    $userId,
                    (int) $objectId,
                    'view',
                    false,
                    'Insufficient clearance level'
                );

                // Return 403 Forbidden
                $response = new Response();
                $response->getBody()->write(json_encode([
                    'error' => 'Access denied',
                    'message' => 'You do not have sufficient security clearance to access this resource.',
                ]));

                return $response
                    ->withStatus(403)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Log successful access
            SecurityClearanceService::logAccessAttempt($userId, (int) $objectId, 'view', true);
        }

        return $handler->handle($request);
    }
}
