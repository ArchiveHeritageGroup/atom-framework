<?php

/**
 * Blade template helper functions.
 *
 * These bridge Symfony 1.x globals into the Blade template context.
 * Loaded once by BladeRenderer on initialization.
 */

// Ensure common Symfony helpers (including I18N for __()) are loaded for Blade templates.
// I18N MUST be loaded before any fallback declaration to avoid redeclare conflicts.
if (!function_exists('url_for') || !function_exists('__')) {
    if (class_exists('sfApplicationConfiguration', false)) {
        try {
            sfApplicationConfiguration::getActive()->loadHelpers(['I18N', 'Url', 'Tag', 'Asset', 'Partial', 'Escaping', 'Qubit']);
        } catch (\Throwable $e) {
            // Helpers may already be loaded or unavailable
        }
    }
}

// Fallback __() only if Symfony's I18N helper could not be loaded (e.g. CLI context)
if (!function_exists('__')) {
    function __(string $text, array $args = [], string $catalogue = 'messages'): string
    {
        if (!empty($args)) {
            return strtr($text, $args);
        }

        return $text;
    }
}

if (!function_exists('atom_url')) {
    /**
     * Generate a URL from a named Symfony route.
     *
     * @param string $route  Named route (e.g., 'ahg_vend_list')
     * @param array  $params Route parameters
     */
    function atom_url(string $route, array $params = []): string
    {
        return sfContext::getInstance()->getRouting()->generate($route, $params);
    }
}

if (!function_exists('csp_nonce_attr')) {
    /**
     * Return the CSP nonce as an HTML attribute string.
     *
     * Returns e.g. nonce="abc123" or empty string if no nonce is set.
     */
    function csp_nonce_attr(): string
    {
        $n = sfConfig::get('csp_nonce', '');

        return $n ? preg_replace('/^nonce=/', 'nonce="', $n) . '"' : '';
    }
}

if (!function_exists('atom_flash')) {
    /**
     * Get a flash message from the Symfony user session.
     *
     * @param string $type Flash type (notice, error, success)
     */
    function atom_flash(string $type): ?string
    {
        $user = sfContext::getInstance()->getUser();
        if ($user->hasFlash($type)) {
            return $user->getFlash($type);
        }

        return null;
    }
}
