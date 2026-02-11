<?php

namespace AtomFramework\Http;

use AtomFramework\Http\Compatibility\SfConfigShim;
use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Http\Controllers\ActionBridge;
use AtomFramework\Http\Middleware\CspMiddleware;
use AtomFramework\Http\Middleware\ForceHttpsMiddleware;
use AtomFramework\Http\Middleware\IpWhitelistMiddleware;
use AtomFramework\Http\Middleware\LimitResultsMiddleware;
use AtomFramework\Http\Middleware\LoadSettingsMiddleware;
use AtomFramework\Http\Middleware\MetaMiddleware;
use AtomFramework\Http\Middleware\TransactionMiddleware;
use AtomFramework\Services\ConfigService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;

/**
 * Heratio Application Kernel — standalone Laravel HTTP kernel.
 *
 * Boots a minimal Laravel stack (container + router + middleware pipeline)
 * that can handle requests independently of Symfony. Designed for
 * dual-stack operation: Nginx routes specific paths here while
 * everything else goes through Symfony's index.php.
 *
 * Boot sequence:
 *   1. Register sfConfig shim (if Symfony not loaded)
 *   2. Load database config → boot Capsule
 *   3. Load settings from DB → populate ConfigService
 *   4. Load app.yml settings (CSP, etc.)
 *   5. Create Router with container
 *   6. Register health/status routes
 *   7. Collect routes from enabled plugins
 *   8. Handle(Request) → Response
 */
class Kernel
{
    private Container $container;
    private Router $router;
    private Dispatcher $events;
    private string $rootDir;
    private bool $booted = false;

    /** @var string[] Middleware stack in execution order */
    private array $middleware = [
        LoadSettingsMiddleware::class,
        CspMiddleware::class,
        MetaMiddleware::class,
        LimitResultsMiddleware::class,
    ];

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = $rootDir ?? dirname(__DIR__, 2);

        // If rootDir points to atom-framework/, go up one more level to AtoM root
        if (basename($this->rootDir) === 'atom-framework') {
            $this->rootDir = dirname($this->rootDir);
        }
    }

    /**
     * Boot the application kernel.
     *
     * Initializes the container, database, config, router, and routes.
     * Safe to call multiple times — subsequent calls are no-ops.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // 1. Register sfConfig shim if Symfony isn't loaded
        if (!class_exists('\sfConfig', false)) {
            SfConfigShim::register();
            SfConfigShim::bootstrap($this->rootDir);
        }

        // 2. Boot database
        $this->bootDatabase();

        // 3. Load settings from database
        ConfigService::loadFromDatabase('en');

        // 4. Load app.yml (CSP config, etc.)
        ConfigService::loadFromAppYaml($this->rootDir);

        // 5. Create container and router
        $this->container = new Container();
        Container::setInstance($this->container);

        $this->events = new Dispatcher($this->container);
        $this->router = new Router($this->events, $this->container);

        // Bind core services into container
        $this->container->instance('app', $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Container::class, $this->container);

        // Register dispatchers required by the Router
        $this->container->singleton(
            \Illuminate\Routing\Contracts\CallableDispatcher::class,
            function ($app) {
                return new \Illuminate\Routing\CallableDispatcher($app);
            }
        );
        $this->container->singleton(
            \Illuminate\Routing\Contracts\ControllerDispatcher::class,
            function ($app) {
                return new \Illuminate\Routing\ControllerDispatcher($app);
            }
        );

        // 6. Register built-in routes (health check, etc.)
        $this->registerBuiltinRoutes();

        // 7. Collect routes from enabled plugins
        $this->collectPluginRoutes();

        $this->booted = true;
    }

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (!$this->booted) {
            $this->boot();
        }

        // Create sfContext adapter for standalone mode
        if (!class_exists('\sfContext', false)) {
            SfContextAdapter::create($request);
        }

        // Run through middleware pipeline, then dispatch via router
        try {
            $response = $this->sendThroughPipeline($request);
        } catch (\Exception $e) {
            $response = $this->handleException($e);
        }

        return $response;
    }

    /**
     * Get the Laravel Router instance.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the service container.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the AtoM root directory.
     */
    public function getRootDir(): string
    {
        return $this->rootDir;
    }

    /**
     * Add middleware to the stack.
     */
    public function pushMiddleware(string $middlewareClass): void
    {
        $this->middleware[] = $middlewareClass;
    }

    // ─── Internal Boot Methods ───────────────────────────────────────

    /**
     * Boot the database Capsule using the shared config parser.
     */
    private function bootDatabase(): void
    {
        // Reuse the bootstrap.php function if already loaded
        if (function_exists('atomParseDbConfig')) {
            $config = atomParseDbConfig($this->rootDir);
        } else {
            $config = $this->parseDbConfig();
        }

        if (null === $config) {
            return;
        }

        // Only boot if Capsule isn't already initialized
        // Check if the static instance was already set by bootstrap.php
        if (defined('ATOM_FRAMEWORK_LOADED')) {
            // bootstrap.php already booted Capsule
            return;
        }

        $capsule = new Capsule();
        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    /**
     * Parse DB config from AtoM's config.php (fallback if bootstrap not loaded).
     */
    private function parseDbConfig(): ?array
    {
        $configFile = $this->rootDir . '/config/config.php';
        if (!file_exists($configFile)) {
            return null;
        }

        $config = require $configFile;
        if (!isset($config['all']['propel']['param'])) {
            return null;
        }

        $dbConfig = $config['all']['propel']['param'];
        $dsn = $dbConfig['dsn'] ?? '';

        $database = 'atom';
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            $database = $matches[1];
        }

        $host = 'localhost';
        if (preg_match('/host=([^;]+)/', $dsn, $matches)) {
            $host = $matches[1];
        }

        $port = 3306;
        if (preg_match('/port=([^;]+)/', $dsn, $matches)) {
            $port = (int) $matches[1];
        }

        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $dbConfig['username'] ?? 'atom',
            'password' => $dbConfig['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ];
    }

    /**
     * Register built-in framework routes.
     */
    private function registerBuiltinRoutes(): void
    {
        // Health check endpoint
        $this->router->get('/api/v3/health', function () {
            return new \Illuminate\Http\JsonResponse([
                'status' => 'ok',
                'engine' => 'heratio',
                'version' => $this->getVersion(),
                'timestamp' => date('c'),
            ]);
        })->name('api.health');

        // Framework info endpoint (admin only in future)
        $this->router->get('/api/v3/info', function () {
            return new \Illuminate\Http\JsonResponse([
                'engine' => 'heratio',
                'version' => $this->getVersion(),
                'php' => PHP_VERSION,
                'middleware' => array_map(function ($m) {
                    $parts = explode('\\', $m);

                    return end($parts);
                }, $this->middleware),
                'routes' => count($this->router->getRoutes()),
            ]);
        })->name('api.info');
    }

    /**
     * Collect routes from enabled plugins via RouteCollector.
     */
    private function collectPluginRoutes(): void
    {
        $pluginsDir = $this->rootDir . '/plugins';
        if (!is_dir($pluginsDir)) {
            return;
        }

        try {
            $pdo = Capsule::connection()->getPdo();
            $collector = new RouteCollector($this->router, $pluginsDir, $pdo);
            $collector->collectAll();
        } catch (\Exception $e) {
            // Plugin route collection failed — not fatal for health check
            error_log('[heratio] Route collection failed: ' . $e->getMessage());
        }
    }

    /**
     * Send the request through the middleware pipeline to the router.
     */
    private function sendThroughPipeline(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function ($next, $middlewareClass) {
                return function (Request $request) use ($next, $middlewareClass) {
                    $middleware = new $middlewareClass();

                    return $middleware->handle($request, $next);
                };
            },
            function (Request $request) {
                return $this->dispatchToRouter($request);
            }
        );

        $response = $pipeline($request);

        // Ensure we have a proper Response object
        if (!$response instanceof \Symfony\Component\HttpFoundation\Response) {
            $response = new Response((string) $response);
        }

        return $response;
    }

    /**
     * Dispatch the request to the Laravel router.
     */
    private function dispatchToRouter(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->router->dispatch($request);
    }

    /**
     * Handle an exception during request processing.
     */
    private function handleException(\Exception $e): \Symfony\Component\HttpFoundation\Response
    {
        $status = 500;
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $status = $e->getStatusCode();
        } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $status = 404;
        }

        $body = [
            'error' => true,
            'message' => $e->getMessage(),
            'status' => $status,
        ];

        // Include trace in debug mode
        if (ConfigService::getBool('sf_debug', false)) {
            $body['trace'] = $e->getTraceAsString();
        }

        return new \Illuminate\Http\JsonResponse($body, $status);
    }

    /**
     * Get the framework version from VERSION file.
     */
    private function getVersion(): string
    {
        $versionFile = dirname(__DIR__, 1) . '/../VERSION';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        // Try atom-framework/VERSION
        $fwVersion = dirname(__DIR__) . '/VERSION';
        if (file_exists($fwVersion)) {
            return trim(file_get_contents($fwVersion));
        }

        return 'dev';
    }
}
