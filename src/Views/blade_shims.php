<?php

/**
 * Symfony Template Helper Shims for Heratio Standalone Mode.
 *
 * These functions provide backward compatibility for plugin PHP templates
 * that call Symfony 1.x view helpers (url_for, link_to, slot, etc.)
 * when running through Heratio instead of Symfony.
 *
 * Each function is guarded with function_exists() so it NEVER conflicts
 * with Symfony when the template is rendered through index.php.
 *
 * Loaded by ActionBridge before rendering PHP templates.
 */

// ── Slot System ──────────────────────────────────────────────────────

if (!isset($GLOBALS['_sf_slots'])) {
    $GLOBALS['_sf_slots'] = [];
    $GLOBALS['_sf_slot_stack'] = [];
}

if (!function_exists('slot')) {
    function slot($name)
    {
        $GLOBALS['_sf_slot_stack'][] = $name;
        ob_start();
    }
}

if (!function_exists('end_slot')) {
    function end_slot()
    {
        if (empty($GLOBALS['_sf_slot_stack'])) {
            return;
        }
        $name = array_pop($GLOBALS['_sf_slot_stack']);
        $GLOBALS['_sf_slots'][$name] = ob_get_clean();
    }
}

if (!function_exists('get_slot')) {
    function get_slot($name, $default = '')
    {
        return $GLOBALS['_sf_slots'][$name] ?? $default;
    }
}

if (!function_exists('has_slot')) {
    function has_slot($name)
    {
        return isset($GLOBALS['_sf_slots'][$name]) && '' !== $GLOBALS['_sf_slots'][$name];
    }
}

if (!function_exists('include_slot')) {
    function include_slot($name)
    {
        if (has_slot($name)) {
            echo $GLOBALS['_sf_slots'][$name];
            return true;
        }
        return false;
    }
}

// ── Layout Decoration ────────────────────────────────────────────────

if (!function_exists('decorate_with')) {
    function decorate_with($layout)
    {
        $GLOBALS['_sf_decorator_layout'] = $layout;
    }
}

// ── URL Generation ───────────────────────────────────────────────────

if (!function_exists('url_for')) {
    function url_for($params, $absolute = false)
    {
        if (is_string($params)) {
            if (str_starts_with($params, '@')) {
                $routeName = substr($params, 1);
                $queryParams = [];
                if (str_contains($routeName, '?')) {
                    [$routeName, $qs] = explode('?', $routeName, 2);
                    parse_str($qs, $queryParams);
                }
                if (class_exists('sfContext', false)) {
                    try {
                        return \sfContext::getInstance()->getRouting()->generate($routeName, $queryParams);
                    } catch (\Throwable $e) {
                        // Fall through
                    }
                }
                $url = '/' . str_replace('_', '/', $routeName);
                if (!empty($queryParams)) {
                    $url .= '?' . http_build_query($queryParams);
                }
                return $url;
            }
            if (str_contains($params, '/')) {
                return '/' . ltrim($params, '/');
            }
            return $params;
        }

        if (is_array($params)) {
            $module = '';
            $action = 'index';
            $slug = '';
            $resource = null;

            foreach ($params as $key => $value) {
                if (is_int($key) && is_object($value)) {
                    $resource = $value;
                    unset($params[$key]);
                    break;
                }
            }

            if (isset($params['module'])) {
                $module = $params['module'];
                unset($params['module']);
            }
            if (isset($params['action'])) {
                $action = $params['action'];
                unset($params['action']);
            }
            if (isset($params['slug'])) {
                $slug = $params['slug'];
                unset($params['slug']);
            }

            if ($resource && !$slug) {
                if (isset($resource->slug)) {
                    $slug = $resource->slug;
                } elseif (method_exists($resource, 'getSlug')) {
                    $slug = $resource->getSlug();
                }
            }

            if ($slug && $module) {
                $url = '/' . rawurlencode($slug);
            } elseif ($module && 'index' !== $action) {
                $url = '/' . $module . '/' . $action;
            } elseif ($module) {
                $url = '/' . $module;
            } else {
                $url = '/';
            }

            $remaining = array_filter($params, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
            if (!empty($remaining)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($remaining);
            }

            return $url;
        }

        return '/';
    }
}

// ── Link Generation ──────────────────────────────────────────────────

if (!function_exists('link_to')) {
    function link_to($text, $url, $options = [])
    {
        if (is_array($url) || (is_string($url) && str_starts_with($url, '@'))) {
            $url = url_for($url);
        }
        $attrs = '';
        foreach ($options as $key => $value) {
            $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string) $value) . '"';
        }
        return '<a href="' . htmlspecialchars((string) $url) . '"' . $attrs . '>' . $text . '</a>';
    }
}

// ── Output Escaping ──────────────────────────────────────────────────

if (!function_exists('esc_specialchars')) {
    function esc_specialchars($value)
    {
        if (null === $value) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_entities')) {
    function esc_entities($value)
    {
        if (null === $value) {
            return '';
        }
        return htmlentities((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_raw')) {
    function esc_raw($value)
    {
        return $value;
    }
}

// ── Number / Date Formatting ─────────────────────────────────────────

if (!function_exists('format_number')) {
    function format_number($number, $decimals = 0)
    {
        return number_format((float) $number, $decimals, '.', ',');
    }
}

if (!function_exists('format_date')) {
    function format_date($date, $format = 'f')
    {
        if (empty($date)) {
            return '';
        }
        $ts = is_numeric($date) ? (int) $date : strtotime((string) $date);
        if (!$ts) {
            return (string) $date;
        }
        return match ($format) {
            'f' => date('F j, Y', $ts),
            'D' => date('Y-m-d', $ts),
            'd' => date('m/d/y', $ts),
            default => date('Y-m-d', $ts),
        };
    }
}

// ── Helper Loading ───────────────────────────────────────────────────

if (!function_exists('use_helper')) {
    function use_helper(...$helpers)
    {
        // Most helpers are already shimmed above. No-op.
    }
}

// ── Asset Helpers ────────────────────────────────────────────────────

if (!function_exists('use_stylesheet')) {
    function use_stylesheet($stylesheet, $position = '', $options = [])
    {
        $GLOBALS['_sf_stylesheets'][] = $stylesheet;
    }
}

if (!function_exists('use_javascript')) {
    function use_javascript($javascript, $position = '', $options = [])
    {
        $GLOBALS['_sf_javascripts'][] = $javascript;
    }
}

if (!function_exists('javascript_tag')) {
    function javascript_tag($content)
    {
        $nonce = '';
        if (class_exists('sfConfig', false)) {
            $n = \sfConfig::get('csp_nonce', '');
            if ($n) {
                $nonce = ' ' . preg_replace('/^nonce=/', 'nonce="', $n) . '"';
            }
        }
        return '<script' . $nonce . '>' . $content . '</script>';
    }
}

if (!function_exists('image_tag')) {
    function image_tag($src, $options = [])
    {
        $attrs = '';
        foreach ($options as $key => $value) {
            $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string) $value) . '"';
        }
        return '<img src="' . htmlspecialchars($src) . '"' . $attrs . ' />';
    }
}

if (!function_exists('public_path')) {
    function public_path($file)
    {
        return '/' . ltrim($file, '/');
    }
}

// ── Partial / Component ──────────────────────────────────────────────

if (!function_exists('get_partial')) {
    function get_partial($name, $vars = [])
    {
        if (str_contains($name, '/')) {
            [$module, $partial] = explode('/', $name, 2);
        } else {
            $module = null;
            $partial = $name;
        }

        $partialFile = '_' . $partial . '.php';
        $rootDir = \sfConfig::get('sf_root_dir', '');
        $pluginsDir = $rootDir . '/plugins';

        if ($module) {
            $searchDirs = glob($pluginsDir . '/*/modules/' . $module . '/templates') ?: [];
        } else {
            $searchDirs = glob($pluginsDir . '/ahgThemeB5Plugin/templates') ?: [];
        }

        foreach ($searchDirs as $dir) {
            $file = $dir . '/' . $partialFile;
            if (file_exists($file)) {
                // Auto-inject standard Symfony template variables (matches ActionBridge behavior)
                $adapterClass = \AtomFramework\Http\Compatibility\SfContextAdapter::class;
                if (class_exists($adapterClass, false) && $adapterClass::hasInstance()) {
                    $ctx = $adapterClass::getInstance();
                    if (!isset($vars['sf_user'])) {
                        $vars['sf_user'] = $ctx->getUser();
                    }
                    if (!isset($vars['sf_request'])) {
                        $vars['sf_request'] = $ctx->getRequest();
                    }
                    if (!isset($vars['sf_context'])) {
                        $vars['sf_context'] = $ctx;
                    }
                }
                if (!isset($vars['sf_data'])) {
                    $vars['sf_data'] = new class($vars) {
                        private array $v;
                        public function __construct(array $v) { $this->v = $v; }
                        public function __get(string $n) { return $this->v[$n] ?? null; }
                        public function __isset(string $n): bool { return isset($this->v[$n]); }
                        public function getRaw(string $n) { return $this->v[$n] ?? null; }
                    };
                }
                extract($vars, EXTR_SKIP);
                ob_start();
                try {
                    include $file;
                } catch (\Throwable $e) {
                    ob_end_clean();
                    return '<!-- Partial error: ' . htmlspecialchars($e->getMessage()) . ' -->';
                }
                return ob_get_clean();
            }
        }

        return '';
    }
}

if (!function_exists('include_partial')) {
    function include_partial($name, $vars = [])
    {
        echo get_partial($name, $vars);
    }
}

if (!function_exists('get_component')) {
    function get_component($module, $component, $vars = [])
    {
        return '';
    }
}

if (!function_exists('include_component')) {
    function include_component($module, $component, $vars = [])
    {
        echo get_component($module, $component, $vars);
    }
}

if (!function_exists('get_component_slot')) {
    function get_component_slot($name)
    {
        return '';
    }
}

if (!function_exists('include_component_slot')) {
    function include_component_slot($name)
    {
        // No-op
    }
}

// ── Title ────────────────────────────────────────────────────────────

if (!function_exists('include_title')) {
    function include_title()
    {
        // Always prefer the Heratio SfContextAdapter in standalone mode
        if (class_exists(\AtomFramework\Http\Compatibility\SfContextAdapter::class, false)
            && \AtomFramework\Http\Compatibility\SfContextAdapter::hasInstance()
        ) {
            $title = \AtomFramework\Http\Compatibility\SfContextAdapter::getInstance()
                ->getResponse()
                ->getTitle();

            echo htmlspecialchars((string) $title);
            return;
        }

        // Never call Symfony sfContext here (it may exist but not be initialized for view_instance)
        echo htmlspecialchars((string) (\sfConfig::get('app_site_title', 'AtoM') ?? 'AtoM'));
    }
}

// ── Translation ──────────────────────────────────────────────────────

if (!function_exists('__')) {
    function __($text, $args = [], $catalogue = 'messages')
    {
        if (!empty($args)) {
            return strtr($text, $args);
        }
        return $text;
    }
}
