<?php

namespace AtomFramework\Http;

use Illuminate\Container\Container;
use Illuminate\Routing\Router;

/**
 * Base class for plugin service providers.
 *
 * Optional — existing plugins continue using sfPluginConfiguration unchanged.
 * New plugins can extend this class to register services, routes, and modules
 * using the modern Heratio container/router instead of Symfony events.
 *
 * Convention: plugins place `config/provider.php` returning the FQCN:
 *
 *   <?php return \AhgExample\ExampleServiceProvider::class;
 *
 * The Kernel discovers and loads these during boot.
 */
abstract class ServiceProvider
{
    protected string $rootDir;
    protected string $name;

    public function __construct(string $rootDir, string $name)
    {
        $this->rootDir = $rootDir;
        $this->name = $name;
    }

    /**
     * Register services/bindings into the container.
     *
     * Called once during kernel boot for each provider.
     */
    public function register(Container $container): void
    {
    }

    /**
     * Called after ALL providers have been registered.
     *
     * Use this for setup that depends on other providers' services.
     */
    public function boot(Container $container): void
    {
    }

    /**
     * Declare routes on the Laravel Router.
     *
     * Called after boot() — all services are available.
     */
    public function routes(Router $router): void
    {
    }

    /**
     * Return array of enabled module names.
     *
     * These are merged into sf_enabled_modules for backward compatibility
     * with templates that check module availability.
     *
     * @return string[]
     */
    public function modules(): array
    {
        return [];
    }

    /**
     * Get the plugin root directory.
     */
    public function getRootDir(): string
    {
        return $this->rootDir;
    }

    /**
     * Get the plugin name.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
