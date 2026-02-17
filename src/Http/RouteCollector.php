<?php

namespace AtomFramework\Http;

use Illuminate\Routing\Router;

/**
 * Discover and register routes from enabled AHG plugins.
 *
 * Uses PDO to query atom_plugin (same pattern as ProjectConfiguration)
 * to avoid Symfony autoloader conflicts. For each enabled plugin,
 * checks for routing.yml and loads routes into the Laravel Router.
 */
class RouteCollector
{
    private Router $router;
    private string $pluginsDir;
    private \PDO $pdo;

    public function __construct(Router $router, string $pluginsDir, \PDO $pdo)
    {
        $this->router = $router;
        $this->pluginsDir = $pluginsDir;
        $this->pdo = $pdo;
    }

    /**
     * Discover and register routes from all enabled plugins.
     */
    public function collectAll(): void
    {
        $plugins = $this->getEnabledPlugins();

        foreach ($plugins as $pluginName) {
            $this->collectFromPlugin($pluginName);
        }
    }

    /**
     * Collect routes from a single plugin.
     */
    public function collectFromPlugin(string $pluginName): void
    {
        $pluginDir = $this->pluginsDir . '/' . $pluginName;
        if (!is_dir($pluginDir)) {
            return;
        }

        // Check for a Laravel routes file (new convention)
        $laravelRoutes = $pluginDir . '/config/routes.php';
        if (file_exists($laravelRoutes)) {
            $router = $this->router;
            require $laravelRoutes;

            return;
        }

        // Check for routing.yml (Symfony convention)
        $routingYml = $pluginDir . '/config/routing.yml';
        if (file_exists($routingYml)) {
            $this->loadFromYaml($routingYml, $pluginName);
        }

        // Check for programmatic routes in Configuration class
        // Some plugins (Reports, Spectrum) define routes via RouteLoader in
        // their Configuration class's loadRoutes/addRoutes method.
        $this->loadFromConfiguration($pluginDir, $pluginName);
    }

    /**
     * Load routes defined programmatically in a plugin's Configuration class.
     *
     * Detects plugins that use RouteLoader in their loadRoutes/addRoutes method
     * and invokes registration with the Laravel router instead of Symfony routing.
     */
    private function loadFromConfiguration(string $pluginDir, string $pluginName): void
    {
        // Find Configuration class file
        $configFile = $pluginDir . '/config/' . $pluginName . 'Configuration.class.php';
        if (!file_exists($configFile)) {
            return;
        }

        $className = $pluginName . 'Configuration';

        // Only load if the class isn't already defined
        if (!class_exists($className, false)) {
            try {
                require_once $configFile;
            } catch (\Throwable $e) {
                // Configuration class may depend on Symfony — skip silently
                return;
            }
        }

        if (!class_exists($className, false)) {
            return;
        }

        // Check if the class has a route registration method
        $routeMethod = null;
        foreach (['loadRoutes', 'addRoutes', 'configureRouting', 'routingLoadConfiguration'] as $method) {
            if (method_exists($className, $method)) {
                $routeMethod = $method;
                break;
            }
        }

        if (!$routeMethod) {
            return;
        }

        // Ensure route class stubs exist for standalone mode
        $this->ensureRouteStubs();

        // Create a mock sfRouting (extends sfRouting to satisfy type hints)
        // that captures route registrations and forwards to Laravel router.
        try {
            $fakeRouting = $this->createRoutingCapture();

            // Ensure sfEvent is loaded — Configuration methods have sfEvent type hints.
            // The class may not be loaded yet (heratio.php doesn't use Symfony autoloader)
            // but it exists in Symfony's vendor directory.
            if (!class_exists('sfEvent', false)) {
                $sfEventFile = dirname($this->pluginsDir) . '/vendor/symfony/lib/event_dispatcher/sfEvent.php';
                if (file_exists($sfEventFile)) {
                    require_once $sfEventFile;
                }
            }

            // Use real sfEvent if available, else anonymous mock (pure standalone mode)
            if (class_exists('sfEvent', false)) {
                $fakeEvent = new \sfEvent($fakeRouting, 'routing.load_configuration');
            } else {
                $fakeEvent = new class($fakeRouting) {
                    private $routing;

                    public function __construct($routing)
                    {
                        $this->routing = $routing;
                    }

                    public function getSubject()
                    {
                        return $this->routing;
                    }
                };
            }

            // Instantiate the Configuration class without full constructor
            $instance = (new \ReflectionClass($className))->newInstanceWithoutConstructor();
            $instance->$routeMethod($fakeEvent);
        } catch (\Throwable $e) {
            // Route loading from Configuration failed — not fatal
            error_log("[heratio] Programmatic route loading failed for {$pluginName}: " . $e->getMessage());
        }
    }

    /**
     * Parse routing.yml and register routes with the Laravel router.
     *
     * Symfony routing.yml format:
     *   route_name:
     *     url: /some/path/:id
     *     param: { module: moduleName, action: actionName }
     *     requirements: { id: \d+ }
     */
    private function loadFromYaml(string $yamlFile, string $pluginName): void
    {
        $content = file_get_contents($yamlFile);
        if (empty($content)) {
            return;
        }

        // Parse YAML — use Symfony YAML component if available
        if (class_exists('\Symfony\Component\Yaml\Yaml')) {
            $routes = \Symfony\Component\Yaml\Yaml::parse($content);
        } elseif (class_exists('\sfYaml')) {
            $routes = \sfYaml::load($yamlFile);
        } else {
            // Fallback: basic YAML parsing for simple route definitions
            $routes = $this->parseSimpleYaml($content);
        }

        if (!is_array($routes)) {
            return;
        }

        $bridgeClass = Controllers\ActionBridge::class;

        foreach ($routes as $routeName => $routeDef) {
            if (!is_array($routeDef) || !isset($routeDef['url'])) {
                continue;
            }

            $url = $routeDef['url'];
            $params = $routeDef['param'] ?? [];
            $requirements = $routeDef['requirements'] ?? [];

            $module = $params['module'] ?? '';
            $action = $params['action'] ?? 'index';

            if (empty($module)) {
                continue;
            }

            // Convert Symfony URL pattern to Laravel: :param → {param}
            $url = preg_replace('/:([a-zA-Z_]+)/', '{$1}', $url);

            // Determine HTTP methods
            $methods = ['GET', 'POST'];
            if (isset($params['sf_method'])) {
                $sfMethods = (array) $params['sf_method'];
                $methods = array_map('strtoupper', $sfMethods);
            }

            $route = $this->router->match($methods, $url, $bridgeClass . '@dispatch')
                ->name($routeName)
                ->setDefaults(['_module' => $module, '_action' => $action]);

            foreach ($requirements as $param => $pattern) {
                if ('sf_method' !== $param) {
                    $route->where($param, $pattern);
                }
            }
        }
    }

    /**
     * Basic YAML parser for simple key: value structures.
     * Only handles the subset needed for routing.yml files.
     */
    private function parseSimpleYaml(string $content): array
    {
        $routes = [];
        $currentRoute = null;
        $currentSection = null;

        foreach (explode("\n", $content) as $line) {
            $trimmed = rtrim($line);
            if (empty($trimmed) || '#' === $trimmed[0]) {
                continue;
            }

            // Top-level route name (no indentation)
            if (!ctype_space($trimmed[0]) && str_ends_with($trimmed, ':')) {
                $currentRoute = rtrim($trimmed, ':');
                $routes[$currentRoute] = [];
                $currentSection = null;

                continue;
            }

            if (null === $currentRoute) {
                continue;
            }

            // Route property: url, class, or section header
            if (preg_match('/^\s{2}(\w+):\s*(.*)$/', $trimmed, $m)) {
                $key = $m[1];
                $value = trim($m[2]);

                if ('' === $value) {
                    // Section header (param:, requirements:)
                    $currentSection = $key;
                    $routes[$currentRoute][$key] = [];
                } elseif (str_starts_with($value, '{')) {
                    // Inline hash: { module: name, action: act }
                    $routes[$currentRoute][$key] = $this->parseInlineHash($value);
                    $currentSection = null;
                } else {
                    $routes[$currentRoute][$key] = $value;
                    $currentSection = null;
                }
            } elseif (null !== $currentSection && preg_match('/^\s{4}(\w+):\s*(.+)$/', $trimmed, $m)) {
                // Sub-property under a section
                $routes[$currentRoute][$currentSection][$m[1]] = trim($m[2]);
            }
        }

        return $routes;
    }

    /**
     * Parse inline YAML hash: { key: value, key2: value2 }
     */
    private function parseInlineHash(string $value): array
    {
        $result = [];
        $inner = trim($value, '{}');
        foreach (explode(',', $inner) as $pair) {
            $parts = explode(':', $pair, 2);
            if (2 === count($parts)) {
                $result[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $result;
    }

    /**
     * Ensure sfRoute and sfRouting classes exist for standalone mode.
     *
     * Plugins that define routes programmatically call new \sfRoute(...)
     * via RouteLoader::register(\sfRouting). In standalone Heratio mode,
     * the Symfony routing library may not be loaded, so we provide stubs.
     */
    private function ensureRouteStubs(): void
    {
        if (!class_exists('sfRoute', false)) {
            eval('
            class sfRoute {
                private $pattern;
                private $defaults;
                private $requirements;

                public function __construct(string $pattern, array $defaults = [], array $requirements = []) {
                    $this->pattern = $pattern;
                    $this->defaults = $defaults;
                    $this->requirements = $requirements;
                }

                public function getPattern(): string { return $this->pattern; }
                public function getDefaults(): array { return $this->defaults; }
                public function getRequirements(): array { return $this->requirements; }
            }
            ');
        }

        if (!class_exists('sfRouting', false)) {
            eval('
            class sfRouting {
                public function prependRoute(string $name, $route): void {}
                public function connect(string $name, $route): void {}
                public function hasRouteName(string $name): bool { return false; }
            }
            ');
        }

        // QubitRoute and QubitResourceRoute extend sfRoute — used by some manage plugins
        if (!class_exists('QubitRoute', false)) {
            eval('class QubitRoute extends sfRoute {}');
        }
        if (!class_exists('QubitResourceRoute', false)) {
            eval('class QubitResourceRoute extends QubitRoute {}');
        }
    }

    /**
     * Create a mock sfRouting subclass that captures routes for Laravel registration.
     */
    private function createRoutingCapture(): object
    {
        $router = $this->router;

        // Must extend sfRouting to satisfy RouteLoader::register() type hint.
        // The real sfRouting (from Symfony vendor) is abstract with 7 abstract
        // methods, so we implement them all as no-ops. We skip parent::__construct()
        // since it requires sfEventDispatcher which we don't have.
        return new class($router) extends \sfRouting {
            private \Illuminate\Routing\Router $lr;

            public function __construct(\Illuminate\Routing\Router $router)
            {
                // Intentionally skip parent::__construct() — it requires sfEventDispatcher
                $this->lr = $router;
            }

            public function prependRoute(string $name, $route): void
            {
                if (is_object($route) && method_exists($route, 'getDefaults')) {
                    $defaults = $route->getDefaults();
                    $url = $route->getPattern();
                    $requirements = $route->getRequirements();
                } else {
                    return;
                }

                $module = $defaults['module'] ?? '';
                $action = $defaults['action'] ?? 'index';
                if (empty($module)) {
                    return;
                }

                $url = preg_replace('/:([a-zA-Z_]+)/', '{$1}', $url);

                $methods = ['GET', 'POST'];
                if (isset($defaults['sf_method'])) {
                    $methods = array_map('strtoupper', (array) $defaults['sf_method']);
                }

                $bridge = \AtomFramework\Http\Controllers\ActionBridge::class;

                try {
                    $laravelRoute = $this->lr->match($methods, $url, $bridge . '@dispatch')
                        ->name($name)
                        ->setDefaults(['_module' => $module, '_action' => $action]);

                    foreach ($requirements as $param => $pattern) {
                        if ('sf_method' !== $param) {
                            $laravelRoute->where($param, $pattern);
                        }
                    }
                } catch (\Throwable $e) {
                    // Duplicate route name or other issue — skip
                }
            }

            public function connect(string $name, $route): void
            {
                $this->prependRoute($name, $route);
            }

            public function hasRouteName(string $name): bool
            {
                return false;
            }

            // ── Abstract method stubs (required by sfRouting) ──

            public function getCurrentInternalUri($with_route_name = false)
            {
                return '';
            }

            public function getRoutes()
            {
                return [];
            }

            public function setRoutes($routes)
            {
                return [];
            }

            public function hasRoutes()
            {
                return false;
            }

            public function clearRoutes()
            {
            }

            public function generate($name, $params = [], $absolute = false)
            {
                return '';
            }

            public function parse($url)
            {
                return false;
            }
        };
    }

    /**
     * Get list of enabled plugins from atom_plugin table.
     * Uses PDO directly (same as ProjectConfiguration).
     */
    private function getEnabledPlugins(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT name FROM atom_plugin WHERE is_enabled = 1 ORDER BY load_order ASC, name ASC'
            );
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }
}
