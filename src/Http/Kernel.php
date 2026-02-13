<?php

namespace AtomFramework\Http;

use AtomFramework\Bridges\PropelBridge;
use AtomFramework\Http\Compatibility\EscaperShim;
use AtomFramework\Http\Compatibility\SfConfigShim;
use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Http\Controllers;
use AtomFramework\Http\Controllers\ActionBridge;
use AtomFramework\Http\Middleware\AuthMiddleware;
use AtomFramework\Http\Middleware\CspMiddleware;
use AtomFramework\Http\Middleware\ForceHttpsMiddleware;
use AtomFramework\Http\Middleware\IpWhitelistMiddleware;
use AtomFramework\Http\Middleware\LimitResultsMiddleware;
use AtomFramework\Http\Middleware\LoadSettingsMiddleware;
use AtomFramework\Http\Middleware\MetaMiddleware;
use AtomFramework\Http\Middleware\SessionMiddleware;
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
    private bool $enabled = true;
    private ?array $bootError = null;

    /** @var string[] Middleware stack in execution order */
    private array $middleware = [
        SessionMiddleware::class,      // 1. Start PHP session (shared with Symfony)
        AuthMiddleware::class,         // 2. Validate auth, handle timeout
        LoadSettingsMiddleware::class,  // 3. Load app settings
        CspMiddleware::class,          // 4. CSP nonce
        MetaMiddleware::class,         // 5. Meta info
        LimitResultsMiddleware::class, // 6. Result limits
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
     * Check whether Heratio is enabled.
     *
     * Fail-closed: Heratio must be explicitly enabled via:
     *   1. Environment variable HERATIO_ENABLED=1 (or true/yes/on)
     *   2. Flag file {rootDir}/.heratio_enabled
     *
     * If neither is set, Heratio is disabled and handle() returns 404.
     * This allows instant disable without code changes — just remove
     * the flag file or set HERATIO_ENABLED=0.
     */
    public function isEnabled(): bool
    {
        $env = getenv('HERATIO_ENABLED');
        if ($env !== false) {
            return in_array(strtolower($env), ['1', 'true', 'yes', 'on'], true);
        }

        return file_exists($this->rootDir . '/.heratio_enabled');
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

        // 0. Kill-switch: check if Heratio is enabled
        $this->enabled = $this->isEnabled();
        if (!$this->enabled) {
            $this->booted = true;

            return;
        }

        // 0b. Boot assertions: verify config, DB, required tables, directories
        $bootError = ConfigService::assertBootRequirements($this->rootDir);
        if (null !== $bootError) {
            $this->bootError = $bootError;
            $this->booted = true;

            return;
        }

        // 1. Register shims if Symfony isn't loaded
        if (!class_exists('\sfConfig', false)) {
            SfConfigShim::register();
            SfConfigShim::bootstrap($this->rootDir);
        }

        if (!class_exists('\sfContext', false)) {
            class_alias(SfContextAdapter::class, 'sfContext');
        }

        if (!class_exists('\sfOutputEscaper', false)) {
            EscaperShim::register();
        }

        // Load Symfony template helper shims (url_for, link_to, slot, etc.)
        // Guarded with function_exists() — safe when Symfony is also loaded.
        require_once dirname(__DIR__) . '/Views/blade_shims.php';

        // Register sfProjectConfiguration shim for plugin detection
        if (!class_exists('sfProjectConfiguration', false)) {
            class_alias(Compatibility\SfProjectConfigurationShim::class, 'sfProjectConfiguration');
        }

        // Register ProjectConfiguration shim — AtoM's ProjectConfiguration
        // extends sfProjectConfiguration. Some Qubit models reference it.
        if (!class_exists('ProjectConfiguration', false)) {
            $projectConfigFile = $this->rootDir . '/config/ProjectConfiguration.class.php';
            if (file_exists($projectConfigFile)) {
                require_once $projectConfigFile;
            }
        }

        // 2. Boot database
        $this->bootDatabase();

        // 2b. Boot Propel bridge — enables Qubit* model classes in standalone mode
        PropelBridge::boot($this->rootDir);

        // 2c. Register PSR-4 autoloaders for AHG plugin namespaces
        $this->registerPluginAutoloaders();

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

        // 8. Register catch-all routes for base AtoM modules.
        //    These MUST be registered LAST so plugin routes take priority.
        $this->registerCatchAllRoutes();

        $this->booted = true;
    }

    /**
     * Handle an incoming HTTP request.
     *
     * If Heratio is disabled (kill-switch), returns a 404 response
     * without touching session or database.
     */
    public function handle(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (!$this->booted) {
            $this->boot();
        }

        // Kill-switch: disabled Heratio returns 404 immediately
        if (!$this->enabled) {
            return new Response(
                'Heratio is disabled. All routes served by Symfony.',
                404,
                ['Content-Type' => 'text/plain']
            );
        }

        // Boot assertion failure: render clear error page
        if (null !== $this->bootError) {
            return new Response(
                ConfigService::renderBootErrorPage($this->bootError),
                503,
                ['Content-Type' => 'text/html; charset=utf-8', 'Retry-After' => '30']
            );
        }

        // Bind the captured Request into the container so the Router
        // resolves the real request (not an empty default) when
        // injecting Request into controller/closure parameters.
        $this->container->instance(Request::class, $request);
        $this->container->instance('request', $request);

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
     * Boot the database Capsule using ConfigService (canonical config source).
     */
    private function bootDatabase(): void
    {
        // bootstrap.php already booted Capsule — skip
        if (defined('ATOM_FRAMEWORK_LOADED')) {
            return;
        }

        $config = ConfigService::parseDbConfig($this->rootDir);
        if (null === $config) {
            return;
        }

        $capsule = new Capsule();
        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
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

        // Auth endpoints (standalone login/logout)
        $this->router->post('/auth/login', [Controllers\AuthController::class, 'login'])->name('auth.login');
        $this->router->match(['GET', 'POST'], '/auth/logout', [Controllers\AuthController::class, 'logout'])->name('auth.logout');
        $this->router->get('/auth/me', [Controllers\AuthController::class, 'me'])->name('auth.me');

        // Homepage — dispatches to the default AtoM homepage action
        $bridge = ActionBridge::class;
        $this->router->match(['GET', 'POST'], '/', $bridge . '@dispatch')
            ->setDefaults(['_module' => 'staticpage', '_action' => 'index', 'slug' => 'home'])
            ->name('homepage');
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
     * Register catch-all routes for base AtoM modules.
     *
     * These match the Symfony URL patterns:
     *   /{module}/{action}        → ActionBridge dispatches to module/action
     *   /{module}/{action}/{slug} → ActionBridge dispatches with slug param
     *   /{slug}                   → ActionBridge dispatches to default (slug view)
     *
     * Registered LAST so plugin-specific routes always win.
     */
    private function registerCatchAllRoutes(): void
    {
        $bridge = ActionBridge::class;

        // /{module}/{action}/{slug} — entity view within a module
        $this->router->match(['GET', 'POST'], '/{_module}/{_action}/{slug}', $bridge . '@dispatch')
            ->where('_module', '[a-zA-Z][a-zA-Z0-9]*')
            ->where('_action', '[a-zA-Z][a-zA-Z0-9]*')
            ->where('slug', '.+')
            ->name('atom.module.action.slug');

        // /{module}/{action} — module action (e.g., /user/login, /informationobject/browse)
        $this->router->match(['GET', 'POST'], '/{_module}/{_action}', $bridge . '@dispatch')
            ->where('_module', '[a-zA-Z][a-zA-Z0-9]*')
            ->where('_action', '[a-zA-Z][a-zA-Z0-9]*')
            ->name('atom.module.action');

        // /{slug} — entity view by slug (the default AtoM pattern)
        // This is the lowest-priority route.
        $this->router->match(['GET', 'POST'], '/{slug}', $bridge . '@dispatch')
            ->where('slug', '.+')
            ->setDefaults(['_module' => 'object', '_action' => 'show'])
            ->name('atom.slug');
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

    /**
     * Register PSR-4 autoloaders for AHG plugin namespaced classes.
     *
     * In Symfony mode, plugin classes are autoloaded by Symfony's plugin
     * autoloader. In standalone Heratio mode, we need to register them
     * with Composer's ClassLoader.
     *
     * Pattern: ahg{Name}Plugin/lib/ → Ahg{Name}\ namespace
     * Also registers Ahg{Name}Plugin\ as fallback (some plugins use it).
     */
    private function registerPluginAutoloaders(): void
    {
        $pluginsDir = $this->rootDir . '/plugins';
        $ahgPluginsDir = $this->rootDir . '/atom-ahg-plugins';

        // Get the Composer ClassLoader instance
        $loaders = spl_autoload_functions();
        $classLoader = null;
        foreach ($loaders as $loader) {
            if (is_array($loader) && $loader[0] instanceof \Composer\Autoload\ClassLoader) {
                $classLoader = $loader[0];
                break;
            }
        }

        if (!$classLoader) {
            return;
        }

        // Scan plugin directories (both symlinked plugins/ and source atom-ahg-plugins/)
        $dirs = [];
        if (is_dir($ahgPluginsDir)) {
            $dirs[] = $ahgPluginsDir;
        }

        foreach ($dirs as $baseDir) {
            $entries = scandir($baseDir);
            if (!$entries) {
                continue;
            }

            foreach ($entries as $entry) {
                if ('.' === $entry[0]) {
                    continue;
                }

                // Only process ahg*Plugin directories
                if (!preg_match('/^ahg(.+)Plugin$/', $entry, $m)) {
                    continue;
                }

                $libDir = $baseDir . '/' . $entry . '/lib/';
                if (!is_dir($libDir)) {
                    continue;
                }

                $name = $m[1]; // e.g., "DonorManage", "Core", "Display"

                // Register primary namespace: Ahg{Name}\ → lib/
                $classLoader->addPsr4('Ahg' . $name . '\\', $libDir);

                // Register fallback: Ahg{Name}Plugin\ → lib/
                // Some plugins use the full plugin name as namespace
                $classLoader->addPsr4('Ahg' . $name . 'Plugin\\', $libDir);
            }
        }
    }
}
