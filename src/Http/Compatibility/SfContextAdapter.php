<?php

namespace AtomFramework\Http\Compatibility;

use Illuminate\Http\Request;

/**
 * Standalone sfContext implementation backed by Laravel services.
 *
 * Provides getInstance(), getRequest(), getResponse(), getUser()
 * for code that depends on sfContext when Symfony isn't loaded.
 * Only activated in standalone mode (heratio.php).
 */
class SfContextAdapter
{
    private static ?self $instance = null;

    private SfWebRequestAdapter $request;
    private SfUserAdapter $user;
    private SfResponseAdapter $response;

    /** @var array<string, mixed> Named services */
    private array $services = [];

    /** Current module/action names (set by ActionBridge during dispatch) */
    private string $moduleName = '';
    private string $actionName = '';

    public function __construct(Request $illuminateRequest)
    {
        $this->request = new SfWebRequestAdapter($illuminateRequest);
        $this->user = new SfUserAdapter();
        $this->response = new SfResponseAdapter();
    }

    /**
     * Magic property access for sfContext compatibility.
     *
     * Symfony's sfContext allows $context->user, $context->request, etc.
     * via __get(). sfAction code uses $this->context->user directly.
     */
    public function __get(string $name)
    {
        return match ($name) {
            'user' => $this->user,
            'request' => $this->request,
            'response' => $this->response,
            'controller' => $this->getController(),
            'routing' => $this->getRouting(),
            'logger' => $this->getLogger(),
            'configuration' => $this->getConfiguration(),
            'i18n' => $this->getI18n(),
            default => $this->services[$name] ?? null,
        };
    }

    /**
     * Magic property check for sfContext compatibility.
     */
    public function __isset(string $name): bool
    {
        return in_array($name, ['user', 'request', 'response', 'controller', 'routing', 'logger', 'configuration', 'i18n'])
            || isset($this->services[$name]);
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            throw new \RuntimeException(
                'SfContextAdapter not initialized. Call SfContextAdapter::create() first.'
            );
        }

        return self::$instance;
    }

    /**
     * Create and set the singleton from an Illuminate Request.
     */
    public static function create(Request $request): self
    {
        self::$instance = new self($request);

        return self::$instance;
    }

    /**
     * Check if the context has been initialized.
     */
    public static function hasInstance(): bool
    {
        return null !== self::$instance;
    }

    public function getRequest(): SfWebRequestAdapter
    {
        return $this->request;
    }

    public function getUser(): SfUserAdapter
    {
        return $this->user;
    }

    public function getResponse(): SfResponseAdapter
    {
        return $this->response;
    }

    /**
     * Get the current module name.
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Set the current module name.
     */
    public function setModuleName(string $name): void
    {
        $this->moduleName = $name;
    }

    /**
     * Get the current action name.
     */
    public function getActionName(): string
    {
        return $this->actionName;
    }

    /**
     * Set the current action name.
     */
    public function setActionName(string $name): void
    {
        $this->actionName = $name;
    }

    /**
     * Check if a named service is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * Get a named service.
     */
    public function get(string $name)
    {
        return $this->services[$name] ?? null;
    }

    /**
     * Register a named service.
     */
    public function set(string $name, $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * Get the routing instance (minimal shim for url generation).
     */
    public function getRouting(): SfRoutingAdapter
    {
        if (!isset($this->services['routing'])) {
            $this->services['routing'] = new SfRoutingAdapter();
        }

        return $this->services['routing'];
    }

    /**
     * Get the configuration (returns a minimal adapter).
     */
    public function getConfiguration(): SfConfigurationAdapter
    {
        if (!isset($this->services['configuration'])) {
            $this->services['configuration'] = new SfConfigurationAdapter();
        }

        return $this->services['configuration'];
    }

    /**
     * Stub logger — logs to error_log in standalone mode.
     */
    public function getLogger(): SfLoggerAdapter
    {
        if (!isset($this->services['logger'])) {
            $this->services['logger'] = new SfLoggerAdapter();
        }

        return $this->services['logger'];
    }

    /**
     * Get the event dispatcher (minimal shim for sfComponent::initialize).
     */
    public function getEventDispatcher(): SfEventDispatcherAdapter
    {
        if (!isset($this->services['event_dispatcher'])) {
            $this->services['event_dispatcher'] = new SfEventDispatcherAdapter();
        }

        return $this->services['event_dispatcher'];
    }

    /**
     * Get the controller (minimal shim for sfAction redirect/forward).
     */
    public function getController(): SfControllerAdapter
    {
        if (!isset($this->services['controller'])) {
            $this->services['controller'] = new SfControllerAdapter();
        }

        return $this->services['controller'];
    }

    /**
     * Get the i18n service (translation adapter).
     */
    public function getI18n(): SfI18nAdapter
    {
        if (!isset($this->services['i18n'])) {
            $this->services['i18n'] = new SfI18nAdapter();
        }

        return $this->services['i18n'];
    }

    /**
     * Get the config cache (minimal shim for sfAction::initialize).
     *
     * sfAction::initialize() loads security.yml via $context->getConfigCache().
     * In standalone mode, we skip security config loading.
     */
    public function getConfigCache(): SfConfigCacheAdapter
    {
        if (!isset($this->services['config_cache'])) {
            $this->services['config_cache'] = new SfConfigCacheAdapter();
        }

        return $this->services['config_cache'];
    }

    /**
     * Reset the singleton (for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}

/**
 * Minimal configuration adapter for standalone mode.
 */
class SfConfigurationAdapter
{
    public function isDebug(): bool
    {
        return SfConfigShim::get('sf_debug', false);
    }

    /**
     * Check if a plugin is enabled (reads atom_plugin table).
     */
    public function isPluginEnabled(string $pluginName): bool
    {
        try {
            $row = \Illuminate\Database\Capsule\Manager::table('atom_plugin')
                ->where('name', $pluginName)
                ->where('is_enabled', 1)
                ->first();

            return null !== $row;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get all enabled plugin names.
     */
    public function getPlugins(): array
    {
        try {
            return \Illuminate\Database\Capsule\Manager::table('atom_plugin')
                ->where('is_enabled', 1)
                ->pluck('name')
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get the environment name (dev, prod, etc.).
     */
    public function getEnvironment(): string
    {
        return SfConfigShim::get('sf_environment', 'prod');
    }

    /**
     * Get template directories for a module.
     */
    public function getTemplateDirs(string $moduleName): array
    {
        $dirs = [];
        $pluginsDir = SfConfigShim::get('sf_plugins_dir', '');

        if ($pluginsDir && is_dir($pluginsDir)) {
            $plugins = glob($pluginsDir . '/*/modules/' . $moduleName . '/templates');
            foreach ($plugins as $dir) {
                if (is_dir($dir)) {
                    $dirs[] = $dir;
                }
            }
        }

        return $dirs;
    }
}

/**
 * Minimal routing adapter for standalone URL generation.
 */
class SfRoutingAdapter
{
    /**
     * Generate a URL for a named route.
     *
     * Provides a best-effort translation from Symfony route names to URLs.
     * Named routes (@name) are converted to /name paths with parameter substitution.
     */
    public function generate(string $routeName, array $params = []): string
    {
        // Try the Laravel Router first (routes registered by RouteCollector)
        try {
            $container = \Illuminate\Container\Container::getInstance();
            if ($container && $container->bound(\Illuminate\Routing\Router::class)) {
                $router = $container->make(\Illuminate\Routing\Router::class);
                $route = $router->getRoutes()->getByName($routeName);
                if ($route) {
                    $url = '/' . ltrim($route->uri(), '/');
                    // Substitute parameters
                    foreach ($params as $key => $value) {
                        $url = str_replace('{' . $key . '}', rawurlencode((string) $value), $url);
                    }

                    return $url;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to static mappings
        }

        // Common route name mappings
        $routes = [
            'homepage' => '/',
            'login' => '/user/login',
            'logout' => '/auth/logout',
        ];

        if (isset($routes[$routeName])) {
            $url = $routes[$routeName];
        } else {
            // Convert route_name to /route/name
            $url = '/' . str_replace('_', '/', $routeName);
        }

        // Append parameters as query string
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Check if a named route exists.
     *
     * Used by theme templates to conditionally show menu items
     * based on which plugins have registered routes.
     */
    public function hasRouteName(string $name): bool
    {
        try {
            $container = \Illuminate\Container\Container::getInstance();
            if ($container && $container->bound(\Illuminate\Routing\Router::class)) {
                $router = $container->make(\Illuminate\Routing\Router::class);

                return null !== $router->getRoutes()->getByName($name);
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return false;
    }
}

/**
 * Minimal logger adapter for standalone mode.
 */
class SfLoggerAdapter
{
    public function err(string $message): void
    {
        error_log('[heratio] ERROR: ' . $message);
    }

    public function warning(string $message): void
    {
        error_log('[heratio] WARNING: ' . $message);
    }

    public function info(string $message): void
    {
        error_log('[heratio] INFO: ' . $message);
    }

    public function debug(string $message): void
    {
        if (SfConfigShim::get('sf_debug', false)) {
            error_log('[heratio] DEBUG: ' . $message);
        }
    }
}

/**
 * Minimal event dispatcher adapter for standalone mode.
 *
 * sfComponent::initialize() stores $context->getEventDispatcher() and
 * sfAction uses $this->dispatcher->notify() for logging. This shim
 * makes those calls no-ops in standalone mode.
 */
class SfEventDispatcherAdapter
{
    public function notify($event): void
    {
        // No-op — events are not dispatched in standalone mode
    }

    public function filter($event, $value)
    {
        return $value;
    }

    public function connect(string $name, $listener): void
    {
        // No-op
    }

    public function disconnect(string $name, $listener): bool
    {
        return true;
    }

    public function hasListeners(string $name): bool
    {
        return false;
    }

    public function getListeners(string $name): array
    {
        return [];
    }
}

/**
 * Minimal sfController adapter for standalone mode.
 *
 * sfAction::redirect() and sfAction::forward() delegate to the controller.
 * This adapter captures redirect URLs and forward targets so ActionBridge
 * can handle them after the action throws sfStopException.
 */
class SfControllerAdapter
{
    private ?string $redirectUrl = null;
    private int $redirectStatusCode = 302;
    private ?string $forwardModule = null;
    private ?string $forwardAction = null;

    /**
     * Capture redirect target (sfAction::redirect delegates here).
     */
    public function redirect($url, $delay = 0, $statusCode = 302): void
    {
        // If $url is an array (named route), convert to string
        if (is_array($url)) {
            $routeName = $url['sf_route'] ?? '';
            unset($url['sf_route'], $url['sf_subject']);
            $routing = SfContextAdapter::getInstance()->getRouting();
            $url = $routing->generate($routeName, $url);
        }

        $this->redirectUrl = (string) $url;
        $this->redirectStatusCode = (int) $statusCode;
    }

    /**
     * Capture forward target (sfAction::forward delegates here).
     */
    public function forward($module, $action): void
    {
        $this->forwardModule = $module;
        $this->forwardAction = $action;
    }

    public function hasRedirect(): bool
    {
        return null !== $this->redirectUrl;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl ?? '/';
    }

    public function getRedirectStatusCode(): int
    {
        return $this->redirectStatusCode;
    }

    public function hasForward(): bool
    {
        return null !== $this->forwardModule;
    }

    public function getForwardModule(): string
    {
        return $this->forwardModule ?? '';
    }

    public function getForwardAction(): string
    {
        return $this->forwardAction ?? '';
    }

    /**
     * Reset state for a new request.
     */
    public function reset(): void
    {
        $this->redirectUrl = null;
        $this->redirectStatusCode = 302;
        $this->forwardModule = null;
        $this->forwardAction = null;
    }

    /**
     * Check whether a module/action exists (used by sfAction::forward).
     */
    public function actionExists(string $module, string $action): bool
    {
        return true;
    }

    /**
     * Generate a URL for an internal route (sfController compat).
     */
    public function genUrl($params, $absolute = false): string
    {
        if (is_string($params)) {
            return $params;
        }

        if (is_array($params)) {
            $module = $params['module'] ?? '';
            $action = $params['action'] ?? 'index';
            unset($params['module'], $params['action']);

            $url = "/{$module}/{$action}";
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            return $url;
        }

        return '/';
    }

    /**
     * Get the rendering mode (sfController compat).
     */
    public function getRenderMode(): int
    {
        return 2; // sfView::RENDER_CLIENT
    }

    /**
     * Get the action stack (sfController compat).
     */
    public function getActionStack(): SfActionStackAdapter
    {
        return new SfActionStackAdapter();
    }
}

/**
 * Minimal action stack adapter.
 */
class SfActionStackAdapter
{
    public function getSize(): int
    {
        return 1;
    }

    public function getFirstEntry()
    {
        return null;
    }

    public function getLastEntry()
    {
        return null;
    }
}

/**
 * Minimal config cache adapter for standalone mode.
 *
 * sfAction::initialize() calls $context->getConfigCache()->checkConfig()
 * to load security.yml. In standalone mode, we skip this — AuthMiddleware
 * handles authentication/authorization instead.
 */
class SfConfigCacheAdapter
{
    /**
     * Check if a config file needs reprocessing.
     *
     * Returns false (no cached config file) so sfAction skips the require().
     */
    public function checkConfig(string $configPath, bool $optional = false)
    {
        return false;
    }

    /**
     * Register a config handler.
     */
    public function registerConfigHandler(string $pattern, string $class, array $params = []): void
    {
        // No-op in standalone mode
    }
}

/**
 * Minimal i18n adapter for standalone mode.
 *
 * sfAction code uses $this->context->i18n->__('text') for translations.
 * In standalone mode, pass through the source text (English).
 */
class SfI18nAdapter
{
    /**
     * Translate a string. Returns the source text in standalone mode.
     */
    public function __($text, $args = [], $catalogue = 'messages')
    {
        if (!empty($args)) {
            return strtr($text, $args);
        }

        return $text;
    }
}
