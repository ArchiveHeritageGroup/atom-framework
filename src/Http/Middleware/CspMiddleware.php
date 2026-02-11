<?php

namespace AtomFramework\Http\Middleware;

use AtomFramework\Services\ConfigService;
use Closure;
use Illuminate\Http\Request;

/**
 * Content Security Policy middleware.
 *
 * Replaces QubitCSP. Generates a per-request nonce and sets the
 * CSP response header. The nonce is stored in ConfigService for
 * use in templates via csp_nonce_attr().
 */
class CspMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Only use CSP if b5 theme is active
        if (!ConfigService::getBool('b5_theme', false)) {
            return $next($request);
        }

        $headerName = $this->getCspResponseHeader();
        if (null === $headerName) {
            return $next($request);
        }

        $directives = $this->getCspDirectives();
        if (null === $directives) {
            return $next($request);
        }

        // Generate nonce and store for templates
        $nonce = bin2hex(random_bytes(16));
        ConfigService::set('csp_nonce', 'nonce=' . $nonce);

        $response = $next($request);

        // Skip CSP header for non-HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if (preg_match('~(text/xml|application/json)~', $contentType)) {
            return $response;
        }

        // Set CSP header with nonce
        $directives = str_replace('nonce', 'nonce-' . $nonce, $directives);
        $response->headers->set($headerName, $directives);

        return $response;
    }

    private function getCspResponseHeader(): ?string
    {
        $header = ConfigService::get('csp_response_header', '');
        if (empty($header)) {
            return null;
        }

        $valid = ['Content-Security-Policy-Report-Only', 'Content-Security-Policy'];
        if (!in_array($header, $valid)) {
            return null;
        }

        return $header;
    }

    private function getCspDirectives(): ?string
    {
        $directives = trim(preg_replace('/\s+/', ' ', ConfigService::get('csp_directives', '')));
        if (empty($directives)) {
            return null;
        }

        return $directives;
    }
}
