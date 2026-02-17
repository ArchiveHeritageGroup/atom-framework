<?php

namespace AtomFramework\Http;

/**
 * Static reverse route registry for standalone mode.
 *
 * When routes are registered on the Laravel router (by RouteCollector or
 * Kernel), they also register here so that url_for('@route_name?key=val')
 * can reverse-resolve the named route to its URL pattern.
 *
 * This replaces Symfony's sfRouting::generate() in standalone mode.
 */
class RouteRegistry
{
    /** @var array<string, string> route name → URL pattern (Laravel-style: /actor/{slug}) */
    private static array $routes = [];

    /**
     * Register a named route with its URL pattern.
     *
     * @param string $name       Route name (e.g. 'actor_view_override')
     * @param string $urlPattern URL pattern with {param} placeholders (e.g. '/actor/{slug}')
     */
    public static function register(string $name, string $urlPattern): void
    {
        self::$routes[$name] = $urlPattern;
    }

    /**
     * Resolve a named route to a URL, substituting parameters.
     *
     * Parameters matching {placeholder} in the URL pattern are substituted
     * into the path. Remaining parameters become query string.
     *
     * @param string $name   Route name
     * @param array  $params Key-value pairs for substitution
     * @return string|null   Resolved URL or null if route not found
     */
    public static function resolve(string $name, array $params = []): ?string
    {
        if (!isset(self::$routes[$name])) {
            return null;
        }

        $url = self::$routes[$name];

        // Replace {param} placeholders with actual values
        foreach ($params as $key => $value) {
            if (str_contains($url, '{' . $key . '}')) {
                $url = str_replace('{' . $key . '}', rawurlencode((string) $value), $url);
                unset($params[$key]);
            }
        }

        // Remove unfilled optional parameters {param?}
        $url = preg_replace('/\/?\{[^}]+\?\}/', '', $url);

        // Remove unfilled required parameters (shouldn't normally happen)
        $url = preg_replace('/\{[^}]+\}/', '', $url);
        $url = rtrim($url, '/') ?: '/';

        // Remaining params become query string
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Check if a named route is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$routes[$name]);
    }
}
