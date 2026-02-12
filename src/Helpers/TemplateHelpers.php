<?php

/**
 * Standalone template helper functions for WP4.
 *
 * Provides the same API as Symfony 1.x template helpers for use
 * in standalone mode (heratio.php). In dual-stack mode, Symfony's
 * own helpers are loaded first, so these function_exists() guards
 * prevent redeclaration.
 *
 * Covers: url_for, link_to, include_partial, include_component,
 *         get_component, use_helper, use_stylesheet, use_javascript,
 *         format_date, format_number, image_tag, content_tag,
 *         tag, get_partial, slot, end_slot, has_slot, get_slot,
 *         include_slot, _compute_public_path
 */

use AtomFramework\Http\Controllers\ComponentRenderer;
use AtomFramework\Services\ConfigService;

// ─── URL Generation ────────────────────────────────────────────────────

if (!function_exists('url_for')) {
    /**
     * Generate a URL for a route or resource.
     *
     * In Symfony mode this is provided by the Url helper.
     * In standalone mode we handle the most common patterns:
     *   - String URL: returned as-is
     *   - Named route "@name?params": resolved via sfContext routing (if available)
     *   - Array with 'module'+'action': generates /module/action
     *   - Array with object + 'module': generates /module/slug
     */
    function url_for($routeOrResource, $options = []): string
    {
        // String URL — return as-is (or resolve named route)
        if (is_string($routeOrResource)) {
            if (str_starts_with($routeOrResource, '@')) {
                return _resolve_named_route($routeOrResource);
            }

            return $routeOrResource;
        }

        // Array-based route
        if (is_array($routeOrResource)) {
            $module = $routeOrResource['module'] ?? '';
            $action = $routeOrResource['action'] ?? '';
            $slug = $routeOrResource['slug'] ?? '';

            // Check for object at index 0 (Symfony pattern: [$resource, 'module' => 'x'])
            if (isset($routeOrResource[0]) && is_object($routeOrResource[0])) {
                $obj = $routeOrResource[0];
                $objSlug = '';
                if (isset($obj->slug)) {
                    $objSlug = $obj->slug;
                } elseif (method_exists($obj, 'getSlug')) {
                    $objSlug = $obj->getSlug();
                }

                if ($module && $objSlug) {
                    return '/' . $module . '/' . rawurlencode($objSlug);
                }
                if ($objSlug) {
                    return '/' . rawurlencode($objSlug);
                }
            }

            if ($module && $action) {
                $url = '/' . $module . '/' . $action;
                if ($slug) {
                    $url = '/' . $module . '/' . rawurlencode($slug) . '/' . $action;
                }

                return $url;
            }

            if ($module && $slug) {
                return '/' . $module . '/' . rawurlencode($slug);
            }

            if ($module) {
                return '/' . $module;
            }
        }

        return '/';
    }
}

if (!function_exists('_resolve_named_route')) {
    /**
     * Resolve a named route (@name?params) to a URL.
     */
    function _resolve_named_route(string $route): string
    {
        $route = substr($route, 1); // Remove '@'
        $parts = explode('?', $route, 2);
        $routeName = $parts[0];
        $queryString = $parts[1] ?? '';

        // Try sfContext routing if available
        if (class_exists('sfContext', false)) {
            try {
                $ctx = sfContext::getInstance();
                $params = [];
                if ($queryString) {
                    parse_str($queryString, $params);
                }

                return $ctx->getRouting()->generate($routeName, $params);
            } catch (\Exception $e) {
                // Fall through
            }
        }

        // Fallback: convert route_name to /route/name
        $url = '/' . str_replace('_', '/', $routeName);
        if ($queryString) {
            $url .= '?' . $queryString;
        }

        return $url;
    }
}

// ─── Link Generation ───────────────────────────────────────────────────

if (!function_exists('link_to')) {
    /**
     * Generate an HTML anchor tag.
     *
     * @param string       $text    Link text
     * @param string|array $url     URL or route array
     * @param array        $options HTML attributes
     */
    function link_to($text, $url = '', $options = []): string
    {
        if (is_array($url)) {
            $url = url_for($url);
        }

        $attrs = '';
        if (is_array($options)) {
            foreach ($options as $key => $value) {
                if (is_string($key)) {
                    $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string) $value) . '"';
                }
            }
        } elseif (is_string($options)) {
            $attrs = ' ' . $options;
        }

        return '<a href="' . htmlspecialchars((string) $url) . '"' . $attrs . '>' . $text . '</a>';
    }
}

// ─── Partial/Component Rendering ───────────────────────────────────────

if (!function_exists('include_partial')) {
    /**
     * Render and output a partial template.
     *
     * @param string $templateName "module/partial" or just "_partial"
     * @param array  $vars         Variables to pass
     */
    function include_partial($templateName, $vars = []): void
    {
        echo get_partial($templateName, $vars);
    }
}

if (!function_exists('get_partial')) {
    /**
     * Render a partial template and return as string.
     *
     * @param string $templateName "module/partial" or just "_partial"
     * @param array  $vars         Variables to pass
     */
    function get_partial($templateName, $vars = []): string
    {
        // Parse "module/partial" format
        if (str_contains($templateName, '/')) {
            [$module, $partial] = explode('/', $templateName, 2);
            if (!str_starts_with($partial, '_')) {
                $partial = '_' . $partial;
            }

            return ComponentRenderer::renderPartial($module, $partial, $vars);
        }

        // Just a partial name — try current module context
        if (!str_starts_with($templateName, '_')) {
            $templateName = '_' . $templateName;
        }

        // Try to determine current module from sfContext
        $module = '';
        if (class_exists('sfContext', false)) {
            try {
                $module = sfContext::getInstance()->getModuleName();
            } catch (\Exception $e) {
                // No context
            }
        }

        if ($module) {
            return ComponentRenderer::renderPartial($module, $templateName, $vars);
        }

        return '';
    }
}

if (!function_exists('include_component')) {
    /**
     * Render and output a component (action + template).
     *
     * @param string $module    Module name
     * @param string $component Component name
     * @param array  $vars      Variables to pass
     */
    function include_component($module, $component, $vars = []): void
    {
        echo get_component($module, $component, $vars);
    }
}

if (!function_exists('get_component')) {
    /**
     * Render a component and return as string.
     *
     * @param string $module    Module name
     * @param string $component Component name
     * @param array  $vars      Variables to pass
     */
    function get_component($module, $component, $vars = []): string
    {
        return ComponentRenderer::render($module, $component, $vars);
    }
}

// ─── Slot System ───────────────────────────────────────────────────────

if (!function_exists('slot')) {
    /** @var array<string, string> Slot buffer stack */
    $_atom_slots = [];
    $_atom_slot_stack = [];

    /**
     * Start capturing content for a named slot.
     */
    function slot($name): void
    {
        global $_atom_slot_stack;
        $_atom_slot_stack[] = $name;
        ob_start();
    }
}

if (!function_exists('end_slot')) {
    /**
     * End the current slot capture.
     */
    function end_slot(): void
    {
        global $_atom_slots, $_atom_slot_stack;
        if (empty($_atom_slot_stack)) {
            return;
        }
        $name = array_pop($_atom_slot_stack);
        $_atom_slots[$name] = ob_get_clean();
    }
}

if (!function_exists('has_slot')) {
    /**
     * Check if a named slot has content.
     */
    function has_slot($name): bool
    {
        global $_atom_slots;

        return isset($_atom_slots[$name]) && strlen($_atom_slots[$name]) > 0;
    }
}

if (!function_exists('get_slot')) {
    /**
     * Get the content of a named slot.
     */
    function get_slot($name, $default = ''): string
    {
        global $_atom_slots;

        return $_atom_slots[$name] ?? $default;
    }
}

if (!function_exists('include_slot')) {
    /**
     * Output a named slot.
     */
    function include_slot($name): bool
    {
        global $_atom_slots;
        if (isset($_atom_slots[$name]) && strlen($_atom_slots[$name]) > 0) {
            echo $_atom_slots[$name];

            return true;
        }

        return false;
    }
}

// ─── HTML Tag Helpers ──────────────────────────────────────────────────

if (!function_exists('content_tag')) {
    /**
     * Generate an HTML content tag.
     *
     * @param string       $tag     Tag name (e.g., 'div', 'span')
     * @param string       $content Tag content
     * @param array|string $options HTML attributes
     */
    function content_tag($tag, $content = '', $options = []): string
    {
        return '<' . $tag . _tag_options($options) . '>' . $content . '</' . $tag . '>';
    }
}

if (!function_exists('tag')) {
    /**
     * Generate a self-closing HTML tag.
     *
     * @param string       $tag     Tag name (e.g., 'br', 'hr', 'img')
     * @param array|string $options HTML attributes
     */
    function tag($tag, $options = []): string
    {
        return '<' . $tag . _tag_options($options) . ' />';
    }
}

if (!function_exists('image_tag')) {
    /**
     * Generate an <img> tag.
     */
    function image_tag($source, $options = []): string
    {
        if (!is_array($options)) {
            $options = [];
        }
        $options['src'] = $source;

        if (!isset($options['alt'])) {
            $options['alt'] = '';
        }

        return tag('img', $options);
    }
}

if (!function_exists('_tag_options')) {
    /**
     * Convert options array to HTML attribute string.
     */
    function _tag_options($options): string
    {
        if (is_string($options)) {
            return $options ? ' ' . $options : '';
        }

        if (!is_array($options) || empty($options)) {
            return '';
        }

        $html = '';
        foreach ($options as $key => $value) {
            if (null === $value || false === $value) {
                continue;
            }
            if (true === $value) {
                $html .= ' ' . htmlspecialchars($key);
            } else {
                $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string) $value) . '"';
            }
        }

        return $html;
    }
}

// ─── Asset Helpers (no-ops in standalone) ──────────────────────────────

if (!function_exists('use_helper')) {
    /**
     * Load a helper group. No-op in standalone mode.
     */
    function use_helper(...$helpers): void
    {
        // In Symfony mode, this is handled by sfApplicationConfiguration.
        // In standalone, helpers are auto-loaded — nothing to do.
    }
}

if (!function_exists('use_stylesheet')) {
    /**
     * Add a stylesheet to the response. No-op in standalone (assets handled by layout).
     */
    function use_stylesheet($css, $position = '', $options = []): void
    {
        // In Symfony mode, queued by sfWebResponse.
        // In standalone mode, assets are handled by the layout template.
    }
}

if (!function_exists('use_javascript')) {
    /**
     * Add a JavaScript to the response. No-op in standalone.
     */
    function use_javascript($js, $position = '', $options = []): void
    {
        // In Symfony mode, queued by sfWebResponse.
        // In standalone mode, assets are handled by the layout template.
    }
}

// ─── Date Formatting ───────────────────────────────────────────────────

if (!function_exists('format_date')) {
    /**
     * Format a date value.
     *
     * @param mixed  $date   Date string, timestamp, or DateTime object
     * @param string $format Format string ('f' = full, 'D' = medium, 'p' = pattern, or PHP date format)
     * @param string $culture Culture code (unused in standalone — uses PHP locale)
     */
    function format_date($date, $format = 'f', $culture = null): string
    {
        if (empty($date)) {
            return '';
        }

        // Convert to timestamp
        if ($date instanceof \DateTimeInterface) {
            $timestamp = $date->getTimestamp();
        } elseif (is_numeric($date)) {
            $timestamp = (int) $date;
        } else {
            $timestamp = strtotime((string) $date);
            if (false === $timestamp) {
                return (string) $date;
            }
        }

        // Map Symfony format codes to PHP date formats
        switch ($format) {
            case 'f':  // full
            case 'F':
                return date('F j, Y g:i A', $timestamp);

            case 'd':  // date only (short)
                return date('Y-m-d', $timestamp);

            case 'D':  // medium date
                return date('M j, Y', $timestamp);

            case 's':  // short
                return date('m/d/Y', $timestamp);

            case 't':  // time only
                return date('g:i A', $timestamp);

            case 'p':  // custom pattern — fallback to ISO
                return date('Y-m-d H:i:s', $timestamp);

            default:
                // Treat as PHP date format string
                return date($format, $timestamp);
        }
    }
}

if (!function_exists('format_number')) {
    /**
     * Format a number.
     */
    function format_number($number, $culture = null): string
    {
        if (!is_numeric($number)) {
            return (string) $number;
        }

        return number_format((float) $number);
    }
}

// ─── Escaping / Public Path ────────────────────────────────────────────

if (!function_exists('_compute_public_path')) {
    /**
     * Compute a public path for an asset.
     */
    function _compute_public_path($source, $dir = '', $ext = ''): string
    {
        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://') || str_starts_with($source, '//')) {
            return $source;
        }

        if (!str_starts_with($source, '/')) {
            $source = '/' . ($dir ? $dir . '/' : '') . $source;
        }

        if ($ext && !str_contains(basename($source), '.')) {
            $source .= '.' . $ext;
        }

        return $source;
    }
}

// ─── Escaping Helper ───────────────────────────────────────────────────

if (!function_exists('esc_entities')) {
    /**
     * Escape HTML entities.
     */
    function esc_entities($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_raw')) {
    /**
     * Return value without escaping (identity function).
     */
    function esc_raw($value)
    {
        return $value;
    }
}
