<?php

namespace AtomFramework\Views;

use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

/**
 * Singleton Blade template renderer for the AtoM AHG Framework.
 *
 * Configures and exposes the Laravel Blade compiler, bridging
 * Symfony helpers via custom directives and shared variables.
 */
class BladeRenderer
{
    private static ?self $instance = null;

    private Factory $factory;

    private BladeCompiler $compiler;

    private FileViewFinder $finder;

    /** @var array<string, bool> Track paths already added to the finder */
    private array $registeredPaths = [];

    private function __construct()
    {
        // Load helper functions
        require_once __DIR__ . '/blade_helpers.php';

        // Setup cache path
        $cachePath = \sfConfig::get('sf_root_dir') . '/cache/blade';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        // Default view paths
        $viewPaths = [
            \sfConfig::get('sf_root_dir') . '/atom-framework/views',
        ];

        // Wire up Illuminate components
        $filesystem = new Filesystem();
        $this->compiler = new BladeCompiler($filesystem, $cachePath);
        $this->finder = new FileViewFinder($filesystem, $viewPaths);
        $dispatcher = new Dispatcher();
        $resolver = new EngineResolver();

        $compiler = $this->compiler;
        $resolver->register('blade', function () use ($compiler, $filesystem) {
            return new CompilerEngine($compiler, $filesystem);
        });
        $resolver->register('php', function () use ($filesystem) {
            return new PhpEngine($filesystem);
        });

        $this->factory = new Factory($resolver, $this->finder, $dispatcher);

        // Register custom directives
        $this->registerDirectives();

        // Share common variables
        $this->shareGlobals();
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Render a Blade template to string.
     *
     * @param string $view Dot-notation view name (e.g., 'layouts.admin')
     * @param array  $data Variables to pass to the template
     */
    public function render(string $view, array $data = []): string
    {
        return $this->factory->make($view, $data)->render();
    }

    /**
     * Add a view path for the file finder.
     *
     * Paths added later take priority (checked first).
     *
     * @param string      $path      Absolute directory path
     * @param string|null $namespace Optional namespace (e.g., 'vendor')
     */
    public function addPath(string $path, ?string $namespace = null): void
    {
        $key = ($namespace ?? '') . ':' . $path;
        if (isset($this->registeredPaths[$key])) {
            return;
        }

        if ($namespace) {
            $this->finder->addNamespace($namespace, $path);
        } else {
            $this->finder->prependLocation($path);
        }

        $this->registeredPaths[$key] = true;
    }

    /**
     * Add a namespaced view path (e.g., 'vendor' => path).
     *
     * Usage in templates: @include('vendor::partials.header')
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->addPath($path, $namespace);
    }

    /**
     * Access the BladeCompiler for custom directive registration.
     */
    public function getCompiler(): BladeCompiler
    {
        return $this->compiler;
    }

    /**
     * Access the ViewFactory.
     */
    public function getFactory(): Factory
    {
        return $this->factory;
    }

    /**
     * Register custom Blade directives bridging Symfony helpers.
     */
    private function registerDirectives(): void
    {
        // @cspNonce — outputs the CSP nonce as an HTML attribute
        $this->compiler->directive('cspNonce', function () {
            return '<?php echo csp_nonce_attr(); ?>';
        });

        // @url('route_name', ['param' => 'value'])
        $this->compiler->directive('url', function ($expression) {
            return "<?php echo atom_url({$expression}); ?>";
        });

        // @trans('Key text') — i18n translation
        $this->compiler->directive('trans', function ($expression) {
            return "<?php echo __({$expression}); ?>";
        });

        // @config('key', 'default') — sfConfig access
        $this->compiler->directive('config', function ($expression) {
            return "<?php echo \\sfConfig::get({$expression}); ?>";
        });

        // @slot_('name') / @endslot_ — bridge to Symfony slot system
        $this->compiler->directive('slot_', function ($expression) {
            return "<?php slot({$expression}); ?>";
        });
        $this->compiler->directive('endslot_', function () {
            return '<?php end_slot(); ?>';
        });

        // @authenticated / @endauthenticated
        $this->compiler->directive('authenticated', function () {
            return '<?php if (\\sfContext::getInstance()->getUser()->isAuthenticated()): ?>';
        });
        $this->compiler->directive('endauthenticated', function () {
            return '<?php endif; ?>';
        });

        // @admin / @endadmin
        $this->compiler->directive('admin', function () {
            return '<?php if (\\sfContext::getInstance()->getUser()->isAdministrator()): ?>';
        });
        $this->compiler->directive('endadmin', function () {
            return '<?php endif; ?>';
        });
    }

    /**
     * Share common variables with all Blade views.
     */
    private function shareGlobals(): void
    {
        $this->factory->share('csp_nonce', csp_nonce_attr());
    }

    /**
     * Reset the singleton (for testing purposes).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
