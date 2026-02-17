<?php

namespace AtomFramework\Http;

use AtomFramework\Http\Compatibility\EscaperShim;
use AtomFramework\Http\Compatibility\SfConfigShim;
use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Http\Controllers;
use AtomFramework\Http\Controllers\ActionBridge;
use AtomFramework\Http\RouteRegistry;
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
    private bool $standaloneMode = false;
    private ?array $bootError = null;

    /** @var ServiceProvider[] Loaded plugin service providers */
    private array $serviceProviders = [];

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

        // Phase 5: Web path is always standalone — Propel not booted.
        // CLI commands that need Propel call PropelBridge::boot() explicitly.
        $this->standaloneMode = true;

        // Register sfProjectConfiguration shim for plugin detection
        if (!class_exists('sfProjectConfiguration', false)) {
            class_alias(Compatibility\SfProjectConfigurationShim::class, 'sfProjectConfiguration');
        }

        // Register ProjectConfiguration — in standalone mode, alias to shim.
        // The real config/ProjectConfiguration.class.php loads sfCoreAutoload
        // which pulls in the entire Symfony autoloader chain, breaking standalone.
        if (!class_exists('ProjectConfiguration', false)) {
            class_alias(Compatibility\SfProjectConfigurationShim::class, 'ProjectConfiguration');
        }

        // Boot standalone compatibility stubs (Qubit models, forms, etc.)
        $this->bootStandaloneCompatibility();

        // 2. Boot database
        $this->bootDatabase();

        // 2c. Register PSR-4 autoloaders for AHG plugin namespaces
        $this->registerPluginAutoloaders();

        // 3. Load settings from database
        ConfigService::loadFromDatabase('en');

        // 4. Load app.yml (CSP config, etc.)
        ConfigService::loadFromAppYaml($this->rootDir);

        // 4b. Initialize plugin configurations (populates sf_enabled_modules,
        //     app_b5_theme, decorator dirs, etc.)
        $this->initializePluginConfigurations();

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

        // 7b. Load ServiceProvider-based plugins (new-style providers)
        $this->loadPluginServiceProviders($this->rootDir . '/plugins');

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
     * Check whether the kernel booted in standalone mode (no Symfony/Propel).
     */
    public function isStandaloneMode(): bool
    {
        return $this->standaloneMode;
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

        // Shim status endpoint — reports standalone boot chain health
        $this->router->get('/api/v3/shim-status', function () {
            $isShimmed = class_exists('sfConfig', false)
                && is_a('sfConfig', SfConfigShim::class, true);

            $modules = \sfConfig::get('sf_enabled_modules', []);
            $cspNonce = \sfConfig::get('csp_nonce', '');
            $b5Theme = \sfConfig::get('app_b5_theme', false);
            $siteTitle = \sfConfig::get('app_siteTitle', '');

            $contextOk = false;
            $userOk = false;
            try {
                if (SfContextAdapter::hasInstance()) {
                    $contextOk = true;
                    $user = SfContextAdapter::getInstance()->getUser();
                    $userOk = $user instanceof Compatibility\SfUserAdapter;
                }
            } catch (\Throwable $e) {
                // ignore
            }

            return new \Illuminate\Http\JsonResponse([
                'standalone' => $isShimmed,
                'standalone_mode' => $this->standaloneMode,
                'shims' => [
                    'sfConfig' => $isShimmed ? 'SfConfigShim' : 'real',
                    'sfContext' => $contextOk ? (is_a('sfContext', SfContextAdapter::class, true) ? 'SfContextAdapter' : 'real') : 'not initialized',
                    'sfProjectConfiguration' => is_a('sfProjectConfiguration', Compatibility\SfProjectConfigurationShim::class, true) ? 'SfProjectConfigurationShim' : 'real',
                    'sfOutputEscaper' => class_exists('sfOutputEscaper', false) ? (is_a('sfOutputEscaper', Compatibility\EscaperShim::class, true) ? 'EscaperShim' : 'real') : 'not loaded',
                    'PropelBridge' => 'disabled (web)',
                ],
                'config' => [
                    'sf_root_dir' => \sfConfig::get('sf_root_dir', '') ? 'set' : 'missing',
                    'sf_plugins_dir' => \sfConfig::get('sf_plugins_dir', '') ? 'set' : 'missing',
                    'sf_upload_dir' => \sfConfig::get('sf_upload_dir', '') ? 'set' : 'missing',
                    'csp_nonce' => $cspNonce ? 'generated' : 'missing',
                    'app_b5_theme' => $b5Theme ? true : false,
                    'app_siteTitle' => $siteTitle ?: 'not set',
                    'sf_enabled_modules' => count($modules),
                ],
                'auth' => [
                    'context_initialized' => $contextOk,
                    'user_adapter' => $userOk,
                    'authenticated' => $contextOk ? SfContextAdapter::getInstance()->getUser()->isAuthenticated() : false,
                ],
                'routes' => count($this->router->getRoutes()),
            ]);
        })->name('api.shim-status');

        // Auth endpoints (standalone login/logout)
        $this->router->match(['GET', 'POST'], '/auth/login', [Controllers\AuthController::class, 'login'])->name('auth.login');
        $this->router->match(['GET', 'POST'], '/auth/logout', [Controllers\AuthController::class, 'logout'])->name('auth.logout');
        $this->router->get('/auth/me', [Controllers\AuthController::class, 'me'])->name('auth.me');

        // Homepage — in standalone mode, render directly via Laravel DB.
        // In dual-stack mode, dispatch to Symfony's staticpage/index action.
        if ($this->standaloneMode) {
            $this->router->match(['GET', 'POST'], '/', function () {
                return $this->renderStandaloneHomepage();
            })->name('homepage');
        } else {
            $bridge = ActionBridge::class;
            $this->router->match(['GET', 'POST'], '/', $bridge . '@dispatch')
                ->setDefaults(['_module' => 'staticpage', '_action' => 'index', 'slug' => 'home'])
                ->name('homepage');
        }
    }

    /**
     * Collect routes from enabled plugins via RouteCollector.
     */
    private function collectPluginRoutes(): void
    {
        $pluginsDir = $this->rootDir . '/plugins';
        if (!is_dir($pluginsDir)) {
            $pluginsDir = $this->rootDir . '/atom-ahg-plugins';
        }
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

        // Register base AtoM URL aliases → AHG manage plugin actions.
        // The menu table stores original AtoM paths (actor/browse, repository/browse, etc.)
        // which must map to the AHG plugin equivalents in standalone mode.
        $bridge = Controllers\ActionBridge::class . '@dispatch';
        $aliases = [
            ['/actor/browse', 'actorManage', 'browse', 'atom_actor_browse'],
            ['/repository/browse', 'repositoryManage', 'browse', 'atom_repository_browse'],
            ['/taxonomy/index', 'termTaxonomy', 'taxonomyIndex', 'atom_taxonomy_index'],
        ];
        foreach ($aliases as [$url, $module, $action, $name]) {
            try {
                $this->router->match(['GET', 'POST'], $url, $bridge)
                    ->name($name)
                    ->setDefaults(['_module' => $module, '_action' => $action]);
                RouteRegistry::register($name, $url);
            } catch (\Throwable $e) {
                // Route may already exist from plugin routing.yml — skip
            }
        }
    }

    /**
     * Boot standalone compatibility stubs.
     *
     * Called when PropelBridge fails (Symfony files absent). Loads minimal
     * stubs so that plugin Configuration classes can be instantiated:
     *   - sfEvent: method signature type hints in plugin configs
     *   - sfSimpleAutoload: called by sfPluginConfiguration::initializeAutoload()
     *   - sfPluginConfiguration: base class for ALL plugin Configuration classes
     *   - sfException: aliased to \Exception
     *   - Qubit model stubs: via src/Compatibility/autoload.php (WP-S2)
     */
    private function bootStandaloneCompatibility(): void
    {
        $compatDir = dirname(__DIR__) . '/Compatibility';

        // Load sfEvent stub (type hint in 73+ plugin Configuration methods)
        if (!class_exists('sfEvent', false)) {
            require_once $compatDir . '/sfEvent.php';
        }

        // Load sfSimpleAutoload stub (used by sfPluginConfiguration::initializeAutoload)
        if (!class_exists('sfSimpleAutoload', false)) {
            require_once $compatDir . '/sfSimpleAutoload.php';
        }

        // Load sfPluginConfiguration stub (base class for ALL plugin configs)
        if (!class_exists('sfPluginConfiguration', false)) {
            require_once $compatDir . '/sfPluginConfiguration.php';
        }

        // Alias Symfony exception classes to \Exception
        if (!class_exists('sfException', false)) {
            class_alias(\Exception::class, 'sfException');
        }
        if (!class_exists('sfStopException', false)) {
            class_alias(\Exception::class, 'sfStopException');
        }
        if (!class_exists('sfForwardException', false)) {
            class_alias(\Exception::class, 'sfForwardException');
        }

        // Register sfWebRequest shim — plugins type-hint sfWebRequest in method
        // signatures. SfWebRequestAdapter conditionally extends this shim so that
        // instanceof/type checks pass in standalone mode.
        if (!class_exists('sfWebRequest', false)) {
            require_once $compatDir . '/sfWebRequest.php';
        }

        // sfView constants — actions return sfView::SUCCESS, sfView::NONE, etc.
        if (!class_exists('sfView', false)) {
            eval('class sfView { const NONE = "None"; const SUCCESS = "Success"; const ERROR = "Error"; const INPUT = "Input"; const HEADER_ONLY = "Header"; }');
        }

        // sfComponent → sfAction → sfActions chain — required by base AtoM action classes
        // (staticpage/indexAction, default/browseAction, etc.) and AhgController dual-stack.
        // These stubs provide the minimal interface that ActionBridge expects.
        if (!class_exists('sfComponent', false)) {
            eval('
            class sfComponent {
                public $context, $request, $response, $dispatcher, $moduleName, $actionName, $varHolder;
                public function __construct($context = null, $moduleName = "", $actionName = "") {
                    $this->context = $context;
                    $this->moduleName = $moduleName;
                    $this->actionName = $actionName;
                    if ($context && method_exists($context, "getRequest")) { $this->request = $context->getRequest(); }
                    if ($context && method_exists($context, "getResponse")) { $this->response = $context->getResponse(); }
                    if ($context && method_exists($context, "getEventDispatcher")) { $this->dispatcher = $context->getEventDispatcher(); }
                    $this->varHolder = new class { private $data = []; public function getAll() { return $this->data; } public function set($k, $v) { $this->data[$k] = $v; } public function get($k, $d = null) { return $this->data[$k] ?? $d; } };
                    if (method_exists($this, "initialize") && $context) { $this->initialize($context, $moduleName, $actionName); }
                }
                public function getContext() { return $this->context; }
                public function getRequest() { return $this->request; }
                public function getResponse() { return $this->response; }
                public function getModuleName() { return $this->moduleName; }
                public function getActionName() { return $this->actionName; }
                public function getUser() { return $this->context ? $this->context->getUser() : null; }
                public function getVarHolder() { return $this->varHolder; }
                public function __set($n, $v) { $this->varHolder->set($n, $v); }
                public function __get($n) { return $this->varHolder->get($n); }
                public function __isset($n) { return $this->varHolder->get($n) !== null; }
                public function getRoute() { return $this->request ? $this->request->getAttribute("sf_route") : null; }
            }');
        }

        if (!class_exists('sfAction', false)) {
            eval('
            class sfAction extends sfComponent {
                public function forward($module, $action) { throw new sfStopException("forward:$module/$action"); }
                public function redirect($url, $statusCode = 302) {
                    if ($this->context && method_exists($this->context, "getController")) {
                        $this->context->getController()->redirect($url, 0, $statusCode);
                    }
                    throw new sfStopException("redirect");
                }
                public function forward404($message = null) { throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException($message ?? "Not found"); }
                public function forward404If($condition, $message = null) { if ($condition) { $this->forward404($message); } }
                public function forward404Unless($condition, $message = null) { if (!$condition) { $this->forward404($message); } }
                public function renderText($text) { if ($this->response) { $this->response->setContent($text); } return sfView::NONE; }
                public function setTemplate($tpl) {}
                public function isSecure() { return false; }
                public function getCredential() { return null; }
            }');
        }

        if (!class_exists('sfActions', false)) {
            eval('
            class sfActions extends sfAction {
                public function execute($request) {
                    $method = "execute" . ucfirst($this->actionName);
                    if (method_exists($this, $method)) { return $this->$method($request); }
                    return null;
                }
                public function preExecute() {}
                public function postExecute() {}
            }');
        }

        // sfComponents — base class for Symfony component actions (get_component() calls)
        if (!class_exists('sfComponents', false)) {
            eval('
            class sfComponents extends sfComponent {
                public function execute($request) {
                    $method = "execute" . ucfirst($this->actionName);
                    if (method_exists($this, $method)) { return $this->$method($request); }
                    return null;
                }
            }');
        }

        // sfBaseTask / sfTask — CLI task base classes (referenced by AhgTask and plugins)
        if (!class_exists('sfTask', false)) {
            eval('
            class sfTask {
                protected $namespace = "";
                protected $name = "";
                protected $briefDescription = "";
                protected $detailedDescription = "";
                public function __construct($dispatcher = null, $formatter = null) {}
                public function run($arguments = [], $options = []) {}
                public function configure() {}
                public function execute($arguments = [], $options = []) {}
                public function log($msg) { error_log($msg); }
                public function logSection($section, $msg) { error_log("[$section] $msg"); }
            }');
        }
        if (!class_exists('sfBaseTask', false)) {
            eval('class sfBaseTask extends sfTask {}');
        }

        // sfConfigurationException — used by AhgMetadataRoute
        if (!class_exists('sfConfigurationException', false)) {
            class_alias(\RuntimeException::class, 'sfConfigurationException');
        }

        // sfCultureInfo — used by theme _layout_start.php for dir="ltr/rtl" on <html>
        // and by repositoryManageActions for getLanguages() / getScripts()
        if (!class_exists('sfCultureInfo', false)) {
            eval('
            class sfCultureInfo {
                public $direction = "ltr";
                private static $instance;
                private $culture;

                public function __construct($culture = "en") { $this->culture = $culture; }

                public static function getInstance($culture = "en") {
                    if (!self::$instance) { self::$instance = new self($culture); }
                    return self::$instance;
                }

                public function getLanguages() {
                    // Return a basic set of common language codes → names
                    return ["en" => "English", "fr" => "French", "de" => "German", "es" => "Spanish",
                            "pt" => "Portuguese", "nl" => "Dutch", "af" => "Afrikaans", "it" => "Italian",
                            "ar" => "Arabic", "zh" => "Chinese", "ja" => "Japanese", "ko" => "Korean",
                            "ru" => "Russian", "sv" => "Swedish", "da" => "Danish", "no" => "Norwegian",
                            "fi" => "Finnish", "pl" => "Polish", "cs" => "Czech", "el" => "Greek",
                            "he" => "Hebrew", "hi" => "Hindi", "hu" => "Hungarian", "ro" => "Romanian",
                            "sk" => "Slovak", "sl" => "Slovenian", "tr" => "Turkish", "uk" => "Ukrainian",
                            "vi" => "Vietnamese", "th" => "Thai", "id" => "Indonesian", "ms" => "Malay",
                            "sw" => "Swahili", "zu" => "Zulu", "xh" => "Xhosa", "st" => "Southern Sotho",
                            "tn" => "Tswana", "ts" => "Tsonga", "ss" => "Swati", "ve" => "Venda",
                            "nr" => "South Ndebele", "nso" => "Northern Sotho"];
                }

                public function getScripts() {
                    return ["Latn" => "Latin", "Cyrl" => "Cyrillic", "Arab" => "Arabic",
                            "Hans" => "Simplified Chinese", "Hant" => "Traditional Chinese",
                            "Grek" => "Greek", "Hebr" => "Hebrew", "Deva" => "Devanagari"];
                }

                public function getCulture() { return $this->culture; }
            }');
        }

        // Load Qubit model stubs (WP-S2 compatibility layer)
        $autoloadFile = $compatDir . '/autoload.php';
        if (file_exists($autoloadFile)) {
            require_once $autoloadFile;
        }

        // Load form system stubs (Phase 4 — replaces sfForm/sfWidget/sfValidator)
        $formAutoload = $compatDir . '/Form/form_autoload.php';
        if (file_exists($formAutoload)) {
            require_once $formAutoload;
        }
    }

    /**
     * Load ServiceProvider-based plugins (new-style providers).
     *
     * Discovers plugins that have a `config/provider.php` file returning
     * a ServiceProvider FQCN. Calls register() on all providers first,
     * then boot() and routes() on each — ensuring cross-provider
     * dependencies are satisfied before boot.
     *
     * Existing sfPluginConfiguration-based plugins are unaffected.
     */
    private function loadPluginServiceProviders(string $pluginsDir): void
    {
        if (!is_dir($pluginsDir)) {
            return;
        }

        // Get enabled + core plugins from DB
        try {
            $enabledPlugins = \Illuminate\Database\Capsule\Manager::table('atom_plugin')
                ->where(function ($q) {
                    $q->where('is_enabled', 1)->orWhere('is_core', 1);
                })
                ->orderBy('load_order')
                ->pluck('name')
                ->toArray();
        } catch (\Throwable $e) {
            return;
        }

        // Phase 1: Discover and register all providers
        foreach ($enabledPlugins as $pluginName) {
            $providerFile = $pluginsDir . '/' . $pluginName . '/config/provider.php';
            if (!file_exists($providerFile)) {
                continue;
            }

            try {
                $fqcn = require $providerFile;
                if (!is_string($fqcn) || !class_exists($fqcn)) {
                    continue;
                }

                $provider = new $fqcn(
                    $pluginsDir . '/' . $pluginName,
                    $pluginName
                );

                if (!$provider instanceof ServiceProvider) {
                    continue;
                }

                $provider->register($this->container);
                $this->serviceProviders[] = $provider;
            } catch (\Throwable $e) {
                error_log('[heratio] ServiceProvider load failed for ' . $pluginName . ': ' . $e->getMessage());
            }
        }

        // Phase 2: Boot and register routes for all providers
        foreach ($this->serviceProviders as $provider) {
            try {
                $provider->boot($this->container);
                $provider->routes($this->router);

                // Merge modules into sf_enabled_modules
                $providerModules = $provider->modules();
                if (!empty($providerModules)) {
                    $current = \sfConfig::get('sf_enabled_modules', []);
                    \sfConfig::set('sf_enabled_modules', array_unique(array_merge($current, $providerModules)));
                }
            } catch (\Throwable $e) {
                error_log('[heratio] ServiceProvider boot failed for ' . $provider->getName() . ': ' . $e->getMessage());
            }
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
     * Render the homepage in standalone mode.
     *
     * Fetches the 'home' static page content from the database and renders
     * it via the heratio Blade layout. Avoids loading the Symfony-dependent
     * StaticPageIndexAction which requires Propel, QubitPdo, Criteria, etc.
     */
    private function renderStandaloneHomepage(): \Symfony\Component\HttpFoundation\Response
    {
        $culture = 'en';
        if (Compatibility\SfContextAdapter::hasInstance()) {
            $culture = Compatibility\SfContextAdapter::getInstance()->getUser()->getCulture();
        }

        $content = '';
        try {
            // Look up the 'home' static page
            $row = \Illuminate\Database\Capsule\Manager::table('slug')
                ->join('static_page_i18n', 'slug.object_id', '=', 'static_page_i18n.id')
                ->where('slug.slug', 'home')
                ->where('static_page_i18n.culture', $culture)
                ->select('static_page_i18n.content', 'static_page_i18n.title')
                ->first();

            if ($row) {
                $content = $row->content ?? '';
            }
        } catch (\Throwable $e) {
            error_log('[heratio] Homepage query failed: ' . $e->getMessage());
        }

        try {
            $renderer = \AtomFramework\Views\BladeRenderer::getInstance();
            $html = $renderer->render('layouts.heratio', [
                'sf_user' => Compatibility\SfContextAdapter::hasInstance()
                    ? Compatibility\SfContextAdapter::getInstance()->getUser() : null,
                'sf_content' => $content,
                'siteTitle' => ConfigService::get('siteTitle', ConfigService::get('app_siteTitle', 'AtoM')),
                'culture' => $culture,
            ]);

            return new Response($html, 200, ['Content-Type' => 'text/html']);
        } catch (\Throwable $e) {
            error_log('[heratio] Homepage render failed: ' . $e->getMessage());

            return new Response(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Home</title>'
                . '<link rel="stylesheet" href="/dist/css/app.css"></head><body>'
                . $content . '</body></html>',
                200,
                ['Content-Type' => 'text/html']
            );
        }
    }

    /**
     * Initialize plugin configuration classes in standalone mode.
     *
     * In Symfony mode, each plugin's Configuration class (e.g.,
     * ahgThemeB5PluginConfiguration) runs initialize() during boot.
     * This populates sf_enabled_modules, sets app_b5_theme, registers
     * decorator dirs, etc.
     *
     * In standalone mode, we replicate this by loading each enabled
     * plugin's Configuration class. The sfPluginConfiguration constructor
     * automatically calls setup(), configure(), initializeAutoload(),
     * and initialize() — so just constructing the class is enough.
     */
    private function initializePluginConfigurations(): void
    {
        // Only run in standalone mode — if real sfProjectConfiguration
        // is loaded (not our shim), Symfony already initialized plugins.
        if (class_exists('sfProjectConfiguration', false)
            && !is_a('sfProjectConfiguration', Compatibility\SfProjectConfigurationShim::class, true)
        ) {
            return;
        }

        // Ensure sf_enabled_modules starts as an array
        \sfConfig::set('sf_enabled_modules', \sfConfig::get('sf_enabled_modules', []));

        $pluginsDir = $this->rootDir . '/plugins';
        if (!is_dir($pluginsDir)) {
            return;
        }

        // Get enabled + core plugins from DB
        try {
            $enabledPlugins = \Illuminate\Database\Capsule\Manager::table('atom_plugin')
                ->where(function ($q) {
                    $q->where('is_enabled', 1)->orWhere('is_core', 1);
                })
                ->orderBy('load_order')
                ->pluck('name')
                ->toArray();
        } catch (\Throwable $e) {
            return;
        }

        $projectConfig = \sfProjectConfiguration::getActive();

        foreach ($enabledPlugins as $pluginName) {
            $configFile = $pluginsDir . '/' . $pluginName . '/config/'
                . $pluginName . 'Configuration.class.php';

            if (!file_exists($configFile)) {
                continue;
            }

            $className = $pluginName . 'Configuration';

            // Skip if already loaded
            if (class_exists($className, false)) {
                continue;
            }

            try {
                require_once $configFile;

                if (!class_exists($className, false)) {
                    continue;
                }

                // Constructing the plugin config automatically calls:
                // setup() → configure() → initializeAutoload() → initialize()
                new $className($projectConfig, $pluginsDir . '/' . $pluginName);
            } catch (\Throwable $e) {
                // Non-fatal — log and continue with remaining plugins
                error_log('[heratio] Plugin config init failed for ' . $pluginName . ': ' . $e->getMessage());
            }
        }
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
