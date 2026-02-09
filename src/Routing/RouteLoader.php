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

            $routing->prependRoute($route['name'], new \sfRoute(
                $route['url'],
                array_merge(
                    ['module' => $this->module, 'action' => $route['action']],
                    $options,
                    $route['defaults']
                ),
                $route['requirements']
            ));
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
