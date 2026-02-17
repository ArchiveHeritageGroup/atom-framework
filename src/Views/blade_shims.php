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

                // Try RouteRegistry reverse lookup (standalone mode)
                if (class_exists(\AtomFramework\Http\RouteRegistry::class, false)
                    && \AtomFramework\Http\RouteRegistry::has($routeName)
                ) {
                    return \AtomFramework\Http\RouteRegistry::resolve($routeName, $queryParams);
                }

                // Try Symfony routing (dual-stack mode)
                if (class_exists('sfContext', false)) {
                    try {
                        return \sfContext::getInstance()->getRouting()->generate($routeName, $queryParams);
                    } catch (\Throwable $e) {
                        // Fall through
                    }
                }

                // Last resort fallback
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
                // In standalone mode, always include /index explicitly
                // because nginx routes require the full path segment.
                $url = '/' . $module . (defined('HERATIO_STANDALONE') ? '/index' : '');
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
        // In standalone mode, intercept layout_start/layout_end to use
        // Heratio Blade partials instead of Symfony theme partials.
        // The Symfony partials use get_component() calls that fail silently.
        if (defined('HERATIO_STANDALONE')) {
            if ('layout_start' === $name) {
                $renderer = \AtomFramework\Views\BladeRenderer::getInstance();
                $adapterClass = \AtomFramework\Http\Compatibility\SfContextAdapter::class;
                $sfUser = (class_exists($adapterClass, false) && $adapterClass::hasInstance())
                    ? $adapterClass::getInstance()->getUser() : null;
                return $renderer->render('partials.layout-start', array_merge($vars, [
                    'sf_user' => $sfUser,
                    'culture' => $sfUser ? $sfUser->getCulture() : 'en',
                ]));
            }
            if ('layout_end' === $name) {
                $renderer = \AtomFramework\Views\BladeRenderer::getInstance();
                return $renderer->render('partials.layout-end', $vars);
            }
        }

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

// ── Form rendering shim ─────────────────────────────────────────────

if (!function_exists('render_field')) {
    /**
     * Minimal standalone shim for render_field().
     *
     * In full Symfony mode, this renders a form field with label/error/help.
     * In standalone Heratio mode, we output a basic Bootstrap 5 form group.
     */
    function render_field($field, $resource = null, array $options = [])
    {
        // Minimal standalone rendering (Symfony's render_field was not loaded,
        // otherwise function_exists guard above would have skipped this definition)
        if (is_object($field) && method_exists($field, 'render')) {
            $label = method_exists($field, 'renderLabel') ? $field->renderLabel() : '';
            $input = $field->render($options);
            $error = method_exists($field, 'renderError') ? $field->renderError() : '';
            return '<div class="mb-3">' . $label . $error . $input . '</div>';
        }

        // String fallback
        return (string) ($field ?? '');
    }
}

if (!function_exists('render_show')) {
    /**
     * Minimal standalone shim for render_show().
     */
    function render_show($label, $value, array $options = [])
    {
        if (empty($value) && '' !== $value) {
            return '';
        }
        $label = htmlspecialchars((string) $label);
        $value = (string) $value;
        return '<div class="field"><h3>' . $label . '</h3><div>' . $value . '</div></div>';
    }
}

if (!function_exists('render_show_repository')) {
    function render_show_repository($label, $resource)
    {
        return '';
    }
}

if (!function_exists('render_value')) {
    function render_value($value)
    {
        return (string) ($value ?? '');
    }
}

if (!function_exists('render_value_inline')) {
    function render_value_inline($value)
    {
        return empty($value) ? '' : esc_specialchars($value);
    }
}

if (!function_exists('render_value_html')) {
    function render_value_html($value)
    {
        return empty($value) ? '' : $value;
    }
}

if (!function_exists('get_search_i18n')) {
    /**
     * Extract an i18n field from a search result document (ES or DB row).
     *
     * Tries culture-specific nested field, direct field, then any-language fallback.
     */
    function get_search_i18n($doc, $field, $options = [])
    {
        $allowEmpty = $options['allowEmpty'] ?? true;

        $culture = 'en';
        if (class_exists('sfContext', false)) {
            try {
                $culture = \sfContext::getInstance()->getUser()->getCulture();
            } catch (\Throwable $e) {
                // fall through
            }
        }
        if (class_exists(\AtomFramework\Http\Compatibility\SfContextAdapter::class, false)
            && \AtomFramework\Http\Compatibility\SfContextAdapter::hasInstance()
        ) {
            try {
                $culture = \AtomFramework\Http\Compatibility\SfContextAdapter::getInstance()
                    ->getUser()->getCulture();
            } catch (\Throwable $e) {
                // fall through
            }
        }

        // Support both array and object (stdClass from DB)
        if (is_object($doc)) {
            // Direct property access for DB row objects
            if (isset($doc->$field) && !empty($doc->$field)) {
                return $doc->$field;
            }
            return $allowEmpty ? '' : '[Untitled]';
        }

        // Try culture-specific nested field
        if (isset($doc['i18n'][$culture][$field]) && !empty($doc['i18n'][$culture][$field])) {
            return $doc['i18n'][$culture][$field];
        }

        // Try direct field
        if (isset($doc[$field]) && !empty($doc[$field])) {
            return $doc[$field];
        }

        // Try any language fallback
        if (isset($doc['i18n']) && is_array($doc['i18n'])) {
            foreach ($doc['i18n'] as $lang => $fields) {
                if (isset($fields[$field]) && !empty($fields[$field])) {
                    return $fields[$field];
                }
            }
        }

        return $allowEmpty ? '' : '[Untitled]';
    }
}

if (!function_exists('check_field_visibility')) {
    function check_field_visibility($fieldName, $options = [])
    {
        return true;
    }
}

if (!function_exists('format_script')) {
    function format_script($script_iso, $culture = null)
    {
        return $script_iso ?? '';
    }
}

if (!function_exists('strip_markdown')) {
    function strip_markdown($text)
    {
        if (empty($text)) {
            return '';
        }
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/[*_]{1,3}([^*_]+)[*_]{1,3}/', '$1', $text);
        $text = preg_replace('/^#+\s*/m', '', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        return strip_tags($text);
    }
}

if (!function_exists('render_title')) {
    /**
     * Minimal standalone shim for render_title().
     *
     * In full Symfony mode, this renders entity title with escaping/truncation.
     * In standalone Heratio mode, we call __toString() with HTML escaping.
     */
    function render_title($resource, $showUntitled = true)
    {
        if (null === $resource) {
            return $showUntitled ? '<em>Untitled</em>' : '';
        }

        if (is_string($resource)) {
            return htmlspecialchars($resource);
        }

        // Try common title properties
        $title = '';
        if (method_exists($resource, '__toString')) {
            $title = (string) $resource;
        } elseif (isset($resource->title)) {
            $title = (string) $resource->title;
        } elseif (isset($resource->authorized_form_of_name)) {
            $title = (string) $resource->authorized_form_of_name;
        } elseif (isset($resource->name)) {
            $title = (string) $resource->name;
        } elseif (isset($resource->slug)) {
            $title = (string) $resource->slug;
        }

        if ('' === $title || null === $title) {
            return $showUntitled ? '<em>Untitled</em>' : '';
        }

        return htmlspecialchars($title);
    }
}

// ── I18N Helpers ─────────────────────────────────────────────────────

if (!function_exists('format_language')) {
    /**
     * Format an ISO 639 language code as a human-readable name.
     *
     * In full Symfony mode, this delegates to sfCultureInfo. In standalone
     * mode, we use a built-in lookup of common language codes.
     */
    function format_language($language_iso, $culture = null)
    {
        if (empty($language_iso)) {
            return '';
        }

        // Try Symfony's sfCultureInfo if available
        if (class_exists('sfCultureInfo', false)) {
            try {
                $c = \sfCultureInfo::getInstance($culture ?? 'en');
                $languages = $c->getLanguages();
                if (isset($languages[$language_iso])) {
                    return $languages[$language_iso];
                }
            } catch (\Throwable $e) {
                // Fall through to built-in lookup
            }
        }

        // Built-in lookup for common language codes
        static $languages = [
            'aa' => 'Afar', 'ab' => 'Abkhazian', 'af' => 'Afrikaans',
            'am' => 'Amharic', 'ar' => 'Arabic', 'as' => 'Assamese',
            'ay' => 'Aymara', 'az' => 'Azerbaijani', 'ba' => 'Bashkir',
            'be' => 'Belarusian', 'bg' => 'Bulgarian', 'bh' => 'Bihari',
            'bn' => 'Bengali', 'bo' => 'Tibetan', 'br' => 'Breton',
            'ca' => 'Catalan', 'co' => 'Corsican', 'cs' => 'Czech',
            'cy' => 'Welsh', 'da' => 'Danish', 'de' => 'German',
            'dz' => 'Dzongkha', 'el' => 'Greek', 'en' => 'English',
            'eo' => 'Esperanto', 'es' => 'Spanish', 'et' => 'Estonian',
            'eu' => 'Basque', 'fa' => 'Persian', 'fi' => 'Finnish',
            'fj' => 'Fijian', 'fo' => 'Faroese', 'fr' => 'French',
            'fy' => 'Western Frisian', 'ga' => 'Irish', 'gd' => 'Scottish Gaelic',
            'gl' => 'Galician', 'gn' => 'Guarani', 'gu' => 'Gujarati',
            'ha' => 'Hausa', 'he' => 'Hebrew', 'hi' => 'Hindi',
            'hr' => 'Croatian', 'hu' => 'Hungarian', 'hy' => 'Armenian',
            'ia' => 'Interlingua', 'id' => 'Indonesian', 'ig' => 'Igbo',
            'is' => 'Icelandic', 'it' => 'Italian', 'ja' => 'Japanese',
            'jv' => 'Javanese', 'ka' => 'Georgian', 'kk' => 'Kazakh',
            'km' => 'Khmer', 'kn' => 'Kannada', 'ko' => 'Korean',
            'ku' => 'Kurdish', 'ky' => 'Kyrgyz', 'la' => 'Latin',
            'lb' => 'Luxembourgish', 'ln' => 'Lingala', 'lo' => 'Lao',
            'lt' => 'Lithuanian', 'lv' => 'Latvian', 'mg' => 'Malagasy',
            'mk' => 'Macedonian', 'ml' => 'Malayalam', 'mn' => 'Mongolian',
            'mr' => 'Marathi', 'ms' => 'Malay', 'mt' => 'Maltese',
            'my' => 'Burmese', 'nb' => 'Norwegian Bokmål', 'nd' => 'North Ndebele',
            'ne' => 'Nepali', 'nl' => 'Dutch', 'nn' => 'Norwegian Nynorsk',
            'no' => 'Norwegian', 'nr' => 'South Ndebele', 'ny' => 'Chewa',
            'oc' => 'Occitan', 'om' => 'Oromo', 'or' => 'Odia',
            'pa' => 'Punjabi', 'pl' => 'Polish', 'ps' => 'Pashto',
            'pt' => 'Portuguese', 'qu' => 'Quechua', 'rm' => 'Romansh',
            'rn' => 'Rundi', 'ro' => 'Romanian', 'ru' => 'Russian',
            'rw' => 'Kinyarwanda', 'sa' => 'Sanskrit', 'sd' => 'Sindhi',
            'si' => 'Sinhala', 'sk' => 'Slovak', 'sl' => 'Slovenian',
            'sn' => 'Shona', 'so' => 'Somali', 'sq' => 'Albanian',
            'sr' => 'Serbian', 'ss' => 'Swati', 'st' => 'Southern Sotho',
            'su' => 'Sundanese', 'sv' => 'Swedish', 'sw' => 'Swahili',
            'ta' => 'Tamil', 'te' => 'Telugu', 'tg' => 'Tajik',
            'th' => 'Thai', 'ti' => 'Tigrinya', 'tk' => 'Turkmen',
            'tl' => 'Tagalog', 'tn' => 'Tswana', 'to' => 'Tongan',
            'tr' => 'Turkish', 'ts' => 'Tsonga', 'tt' => 'Tatar',
            'uk' => 'Ukrainian', 'ur' => 'Urdu', 'uz' => 'Uzbek',
            've' => 'Venda', 'vi' => 'Vietnamese', 'xh' => 'Xhosa',
            'yi' => 'Yiddish', 'yo' => 'Yoruba', 'zh' => 'Chinese',
            'zu' => 'Zulu',
        ];

        return $languages[$language_iso] ?? $language_iso;
    }
}

if (!function_exists('format_country')) {
    function format_country($country_iso, $culture = null)
    {
        return $country_iso ?? '';
    }
}

// ── sfForm Stub ──────────────────────────────────────────────────────

if (!class_exists('sfForm', false)) {
    /**
     * Minimal sfForm stub for standalone mode.
     *
     * Uses __call() to silently handle ANY method call so that form-related
     * code paths don't crash. Provides renderFormTag() for templates that
     * generate form HTML directly.
     */
    class sfForm
    {
        protected array $defaults = [];

        public function __construct($defaults = [], $options = [], $CSRFSecret = null)
        {
            $this->defaults = is_array($defaults) ? $defaults : [];
        }

        public function renderFormTag(string $url, array $attributes = []): string
        {
            $method = $attributes['method'] ?? 'post';
            unset($attributes['method']);
            $attrs = '';
            foreach ($attributes as $k => $v) {
                $attrs .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string) $v) . '"';
            }
            return '<form action="' . htmlspecialchars($url) . '" method="' . htmlspecialchars($method) . '"' . $attrs . '>';
        }

        public function renderHiddenFields(): string { return ''; }
        public function renderGlobalErrors(): string { return ''; }
        public function render(): string { return ''; }
        public function isBound(): bool { return false; }
        public function isValid(): bool { return false; }
        public function hasErrors(): bool { return false; }
        public function getErrorSchema() { return new class { public function __toString(): string { return ''; } }; }

        public function __call(string $method, array $args)
        {
            // Return $this for fluent setters, empty values otherwise
            if (str_starts_with($method, 'set') || str_starts_with($method, 'add')) {
                return $this;
            }
            if (str_starts_with($method, 'get')) {
                return null;
            }
            return $this;
        }

        public function __get(string $name)
        {
            return new class($name) {
                private string $n;
                public function __construct(string $n) { $this->n = $n; }
                public function render(array $attrs = []): string { return '<input type="text" name="' . htmlspecialchars($this->n) . '">'; }
                public function renderLabel(array $attrs = []): string { return '<label>' . htmlspecialchars($this->n) . '</label>'; }
                public function renderError(): string { return ''; }
                public function renderRow(array $attrs = []): string { return $this->renderLabel() . $this->render($attrs); }
                public function __toString(): string { return $this->render(); }
                public function __call(string $m, array $a) { return $this; }
            };
        }

        public function __isset(string $name): bool { return true; }
    }
}
