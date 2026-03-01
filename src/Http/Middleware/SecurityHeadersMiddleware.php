<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Http\Request;

/**
 * Security headers middleware.
 *
 * Adds standard security headers to all responses. These headers provide
 * defense-in-depth against common web attacks (clickjacking, MIME sniffing,
 * XSS, information leakage).
 *
 * Headers set:
 *   - X-Content-Type-Options: nosniff
 *   - X-Frame-Options: SAMEORIGIN
 *   - Referrer-Policy: strict-origin-when-cross-origin
 *   - Permissions-Policy: restrictive defaults
 *   - Strict-Transport-Security: max-age=31536000 (when HTTPS)
 *   - X-Permitted-Cross-Domain-Policies: none
 *
 * These supplement (but do not replace) nginx-level security headers.
 * If the same header is set by nginx and this middleware, the nginx
 * header takes precedence.
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);

        // Prevent clickjacking — only allow framing from same origin
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);

        // Control referrer information sent with requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        // Restrict browser features (camera, microphone, geolocation, etc.)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()', false);

        // Prevent cross-domain policy file loading (Flash/Acrobat)
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none', false);

        // HSTS — only on HTTPS connections
        if ($request->isSecure() || ConfigService::getBool('require_ssl_admin', false)) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
                false
            );
        }

        return $response;
    }
}
