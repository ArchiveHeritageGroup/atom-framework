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
