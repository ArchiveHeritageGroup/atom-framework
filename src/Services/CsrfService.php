<?php

namespace AtomFramework\Services;

/**
 * CSRF Protection Service.
 *
 * Provides per-session CSRF token generation and validation.
 * Tokens rotate after 1 hour. Validation uses constant-time comparison.
 *
 * Enforcement modes (configurable via ahg_settings key 'csrf_enforcement'):
 *   - 'log'     : Log violations but allow request (default, safe rollout)
 *   - 'enforce' : Block requests with invalid/missing tokens (403)
 *   - 'off'     : Disable CSRF checking entirely
 */
class CsrfService
{
    /** Session key for the CSRF token */
    private const SESSION_KEY = '_csrf_token';

    /** Session key for token creation timestamp */
    private const TIMESTAMP_KEY = '_csrf_token_ts';

    /** Token lifetime in seconds (1 hour) */
    private const TOKEN_LIFETIME = 3600;

    /** POST parameter name */
    public const FIELD_NAME = '_csrf_token';

    /** HTTP header name (for AJAX requests) */
    public const HEADER_NAME = 'X-CSRF-TOKEN';

    /**
     * Generate or retrieve the current CSRF token.
     *
     * Creates a new token if none exists or if the current one has expired.
     * Tokens are stored in the PHP session.
     *
     * @return string 64-character hex token
     */
    public static function generateToken(): string
    {
        self::ensureSession();

        $token = $_SESSION[self::SESSION_KEY] ?? null;
        $timestamp = $_SESSION[self::TIMESTAMP_KEY] ?? 0;

        // Rotate if missing or expired
        if ($token === null || (time() - $timestamp) > self::TOKEN_LIFETIME) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
            $_SESSION[self::TIMESTAMP_KEY] = time();
        }

        return $token;
    }

    /**
     * Validate a CSRF token against the session token.
     *
     * Uses hash_equals() for constant-time comparison to prevent timing attacks.
     *
     * @param string $token The token to validate
     * @return bool True if the token is valid
     */
    public static function validateToken(string $token): bool
    {
        self::ensureSession();

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? null;

        if ($sessionToken === null || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Extract CSRF token from the current request.
     *
     * Checks in order:
     *   1. POST body parameter (_csrf_token)
     *   2. HTTP header (X-CSRF-TOKEN)
     *
     * @return string|null The token, or null if not found
     */
    public static function getTokenFromRequest(): ?string
    {
        // Check POST body
        if (isset($_POST[self::FIELD_NAME]) && is_string($_POST[self::FIELD_NAME])) {
            return $_POST[self::FIELD_NAME];
        }

        // Check HTTP header (normalize to HTTP_X_CSRF_TOKEN)
        $headerKey = 'HTTP_' . str_replace('-', '_', strtoupper(self::HEADER_NAME));
        if (isset($_SERVER[$headerKey]) && is_string($_SERVER[$headerKey])) {
            return $_SERVER[$headerKey];
        }

        return null;
    }

    /**
     * Check if the current request is exempt from CSRF validation.
     *
     * Exemptions:
     *   - Requests with Bearer token (API authentication)
     *   - Requests with X-API-Key header
     *   - XMLHttpRequest with custom header (handled by csrf.js)
     *   - Non-mutating methods (GET, HEAD, OPTIONS)
     *
     * @return bool True if the request is exempt
     */
    public static function isExempt(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Safe methods don't need CSRF protection
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        // Bearer token auth (API clients)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (stripos($authHeader, 'Bearer ') === 0) {
            return true;
        }

        // API key auth
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return true;
        }

        return false;
    }

    /**
     * Render a hidden input field containing the CSRF token.
     *
     * @return string HTML hidden input element
     */
    public static function renderHiddenField(): string
    {
        $token = self::generateToken();

        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '" />';
    }

    /**
     * Render a meta tag containing the CSRF token (for JS access).
     *
     * @return string HTML meta element
     */
    public static function getMetaTag(): string
    {
        $token = self::generateToken();

        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '" />';
    }

    /**
     * Get the current enforcement mode.
     *
     * @return string 'log', 'enforce', or 'off'
     */
    public static function getEnforcementMode(): string
    {
        // Try AhgSettingsService if available
        if (class_exists(AhgSettingsService::class)) {
            try {
                $mode = AhgSettingsService::get('csrf_enforcement', 'log');
                if (in_array($mode, ['log', 'enforce', 'off'], true)) {
                    return $mode;
                }
            } catch (\Throwable $e) {
                // Fall through to default
            }
        }

        return 'log';
    }

    /**
     * Enforce CSRF protection on the current request.
     *
     * Call this in controller boot() or middleware. Behavior depends on
     * enforcement mode: log only, enforce (403), or off.
     *
     * @return bool True if the request is allowed to proceed
     */
    public static function enforce(): bool
    {
        $mode = self::getEnforcementMode();

        if ($mode === 'off') {
            return true;
        }

        if (self::isExempt()) {
            return true;
        }

        $token = self::getTokenFromRequest();

        if ($token === null || !self::validateToken($token)) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';

            error_log(sprintf(
                'CSRF violation: %s %s (token %s, mode: %s)',
                $requestMethod,
                $requestUri,
                $token === null ? 'missing' : 'invalid',
                $mode
            ));

            if ($mode === 'enforce') {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure the PHP session is active.
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Don't start a new session if headers already sent
            if (!headers_sent()) {
                session_start();
            }
        }
    }
}
