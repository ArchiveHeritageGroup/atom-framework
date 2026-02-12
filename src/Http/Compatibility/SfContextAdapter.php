<?php

namespace AtomFramework\Http\Compatibility;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    private Response $response;

    /** @var array<string, mixed> Named services */
    private array $services = [];

    public function __construct(Request $illuminateRequest)
    {
        $this->request = new SfWebRequestAdapter($illuminateRequest);
        $this->user = new SfUserAdapter();
        $this->response = new Response();
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

    public function getResponse(): Response
    {
        return $this->response;
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
