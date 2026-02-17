<?php

namespace AtomFramework\Routing;

/**
 * Fluent route definition for AHG plugins.
 *
 * Modernizes route registration — plugins define routes in PHP with a clean
 * API, and RouteLoader handles Symfony sfRouting registration.
 *
 * Usage:
 *   public function configureRouting(sfEvent $event) {
 *       $router = new RouteLoader('donorAgreement');
 *       $router->get('donor_dashboard', '/donor/dashboard', 'dashboard');
 *       $router->get('donor_browse', '/donor/browse', 'browse');
 *       $router->any('donor_delete', '/donor/:id/delete', 'delete', ['id' => '\d+']);
 *       $router->register($event->getSubject());
 *   }
 */
class RouteLoader
{
    private array $routes = [];
    private string $module;

    public function __construct(string $module)
    {
        $this->module = $module;
    }

    /**
     * Register a GET route.
     */
    public function get(string $name, string $url, string $action, array $requirements = [], array $defaults = []): self
    {
        return $this->addRoute($name, $url, $action, $requirements, ['get'], $defaults);
    }

    /**
     * Register a POST route.
     */
    public function post(string $name, string $url, string $action, array $requirements = [], array $defaults = []): self
    {
        return $this->addRoute($name, $url, $action, $requirements, ['post'], $defaults);
    }

    /**
     * Register a route for any HTTP method.
     */
    public function any(string $name, string $url, string $action, array $requirements = [], array $defaults = []): self
    {
        return $this->addRoute($name, $url, $action, $requirements, [], $defaults);
    }

    /**
     * Register all defined routes with the Symfony routing system.
     */
    public function register(\sfRouting $routing): void
    {
        foreach ($this->routes as $route) {
            $options = [];
            if (!empty($route['methods'])) {
                $options['sf_method'] = $route['methods'];
            }

            $routeDefaults = array_merge(
                ['module' => $this->module, 'action' => $route['action']],
                $options,
                $route['defaults']
            );

            $routing->prependRoute($route['name'], new \sfRoute(
                $route['url'],
                $routeDefaults,
                $route['requirements']
            ));

            // Also register trailing-slash variant so /path/ matches /path
            if (!str_ends_with($route['url'], '/')) {
                $routing->prependRoute($route['name'] . '_ts', new \sfRoute(
                    $route['url'] . '/',
                    $routeDefaults,
                    $route['requirements']
                ));
            }
        }
    }

    /**
     * Register all defined routes with the Laravel router.
     *
     * Converts Symfony-style URL patterns (:param) to Laravel-style ({param})
     * and registers them with the Illuminate Router. The ActionBridge handles
     * dispatch to the actual plugin action classes.
     */
    public function registerLaravel(\Illuminate\Routing\Router $router, string $bridgeClass = null): void
    {
        $bridgeClass = $bridgeClass ?? \AtomFramework\Http\Controllers\ActionBridge::class;
        $module = $this->module;

        foreach ($this->routes as $route) {
            // Convert Symfony URL patterns to Laravel: :param → {param}
            $url = preg_replace('/:([a-zA-Z_]+)/', '{$1}', $route['url']);

            // Build the route handler
            $handler = $bridgeClass . '@dispatch';
            $defaults = array_merge($route['defaults'], [
                '_module' => $module,
                '_action' => $route['action'],
            ]);

            // Add requirements as where constraints
            $methods = !empty($route['methods']) ? $route['methods'] : ['GET', 'POST'];

            $laravelRoute = $router->match(
                array_map('strtoupper', $methods),
                $url,
                $handler
            )->name($route['name'])->setDefaults($defaults);

            foreach ($route['requirements'] as $param => $pattern) {
                $laravelRoute->where($param, $pattern);
            }
        }
    }

    /**
     * Register routes for a direct AhgController (WP2).
     *
     * Maps route definitions directly to controller methods without ActionBridge.
     * The controller class must extend AhgController and have execute{Action}() methods.
     *
     * @param \Illuminate\Routing\Router $router          Laravel router
     * @param string                     $controllerClass Fully qualified class name
     */
    public function registerController(\Illuminate\Routing\Router $router, string $controllerClass): void
    {
        $module = $this->module;

        foreach ($this->routes as $route) {
            // Convert Symfony URL patterns to Laravel: :param → {param}
            $url = preg_replace('/:([a-zA-Z_]+)/', '{$1}', $route['url']);

            // Build direct closure handler that instantiates the controller
            $action = $route['action'];
            $handler = function (\Illuminate\Http\Request $request) use ($controllerClass, $action, $module) {
                $sfRequest = new \AtomFramework\Http\Compatibility\SfWebRequestAdapter($request);

                // Set route parameters
                $routeParams = $request->route() ? $request->route()->parameters() : [];
                foreach ($routeParams as $key => $value) {
                    if ('_module' !== $key && '_action' !== $key) {
                        $sfRequest->setParameter($key, $value);
                    }
                }
                $sfRequest->setParameter('module', $module);
                $sfRequest->setParameter('action', $action);

                $instance = new $controllerClass();

                return $instance->dispatch($action, $sfRequest, $module);
            };

            $methods = !empty($route['methods']) ? $route['methods'] : ['GET', 'POST'];

            $laravelRoute = $router->match(
                array_map('strtoupper', $methods),
                $url,
                $handler
            )->name($route['name']);

            foreach ($route['requirements'] as $param => $pattern) {
                $laravelRoute->where($param, $pattern);
            }
        }
    }

    /**
     * Get defined routes (for inspection/testing).
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Add a route definition.
     */
    private function addRoute(string $name, string $url, string $action, array $requirements, array $methods = [], array $defaults = []): self
    {
        $this->routes[] = [
            'name' => $name,
            'url' => $url,
            'action' => $action,
            'requirements' => $requirements,
            'methods' => $methods,
            'defaults' => $defaults,
        ];

        return $this;
    }
}
