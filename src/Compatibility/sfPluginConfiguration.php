<?php

/**
 * sfPluginConfiguration stub for standalone Heratio mode.
 *
 * Provides the abstract base class that ALL plugin Configuration classes
 * extend. Required because every plugin has a Configuration class like:
 *
 *   class ahgThemeB5PluginConfiguration extends sfPluginConfiguration { ... }
 *
 * Without this stub, PHP fatals on class load when Symfony is absent.
 *
 * API-compatible with vendor/symfony/lib/config/sfPluginConfiguration.class.php.
 * In dual-stack mode (Symfony present), the real sfPluginConfiguration is loaded
 * by sfCoreAutoload and this file is never included.
 *
 * Safety: The real constructor checks `if (!$this->configuration instanceof
 * sfApplicationConfiguration)` before calling initializeAutoload()/initialize().
 * In standalone mode, sfApplicationConfiguration doesn't exist, so PHP's
 * `instanceof` returns false → `!false = true` → both methods always run.
 * This is correct behavior — we WANT plugin initialization in standalone mode.
 */
abstract class sfPluginConfiguration
{
    protected $configuration = null;
    protected $dispatcher = null;
    protected $name = null;
    protected $rootDir = null;

    /**
     * Constructor — matches real sfPluginConfiguration signature exactly.
     *
     * @param sfProjectConfiguration $configuration The project configuration
     * @param string|null            $rootDir       The plugin root directory
     * @param string|null            $name          The plugin name
     */
    public function __construct(sfProjectConfiguration $configuration, $rootDir = null, $name = null)
    {
        $this->configuration = $configuration;
        $this->dispatcher = $configuration->getEventDispatcher();
        $this->rootDir = null === $rootDir ? $this->guessRootDir() : realpath($rootDir);
        $this->name = null === $name ? $this->guessName() : $name;

        $this->setup();
        $this->configure();

        // The real sfPluginConfiguration checks:
        //   if (!$this->configuration instanceof sfApplicationConfiguration)
        // In standalone mode, sfApplicationConfiguration doesn't exist.
        // PHP instanceof against undefined class returns false.
        // !false = true → initializeAutoload() and initialize() always run.
        if (!$this->configuration instanceof \sfApplicationConfiguration) {
            $this->initializeAutoload();
            $this->initialize();
        }
    }

    /**
     * Sets up the plugin.
     *
     * Called before configure(). Base plugins can override for shared setup.
     */
    public function setup()
    {
    }

    /**
     * Configures the plugin.
     *
     * Called before autoloading — classes may not be available yet.
     */
    public function configure()
    {
    }

    /**
     * Initializes the plugin.
     *
     * Called after autoloading — all classes are available.
     */
    public function initialize()
    {
    }

    /**
     * Returns the plugin root directory.
     *
     * @return string
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }

    /**
     * Returns the plugin name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Initializes autoloading for the plugin.
     *
     * Uses sfSimpleAutoload to scan the plugin's lib/ directory.
     */
    public function initializeAutoload()
    {
        $cacheDir = \sfConfig::get('sf_cache_dir', sys_get_temp_dir());
        $autoload = \sfSimpleAutoload::getInstance($cacheDir . '/project_autoload.cache');

        if (is_readable($file = $this->rootDir . '/config/autoload.yml')) {
            // In standalone mode, loadConfiguration is a no-op (no sfAutoloadConfigHandler).
            // Fall through to addDirectory() below for basic autoloading.
            $this->configuration->getEventDispatcher()->connect('autoload.filter_config', [$this, 'filterAutoloadConfig']);
            $autoload->loadConfiguration([$file]);
            $this->configuration->getEventDispatcher()->disconnect('autoload.filter_config', [$this, 'filterAutoloadConfig']);
        }

        // Always add the lib/ directory — this ensures class autoloading works
        // even when loadConfiguration is a no-op in standalone mode
        $libDir = $this->rootDir . '/lib';
        if (is_dir($libDir)) {
            $autoload->addDirectory($libDir);
        }

        $autoload->register();
    }

    /**
     * Filters sfAutoload configuration values.
     *
     * @param sfEvent $event
     * @param array   $config
     *
     * @return array
     */
    public function filterAutoloadConfig(sfEvent $event, array $config)
    {
        if (!isset($config['autoload'][$this->name . '_lib'])) {
            $config['autoload'] = array_merge([
                $this->name => [
                    'path' => $this->rootDir . '/lib',
                    'recursive' => true,
                    'exclude' => ['vendor'],
                ],
            ], $config['autoload']);
        }

        if (!isset($config['autoload'][$this->name . '_modules'])) {
            $config['autoload'] = array_merge([
                $this->name . '_modules' => [
                    'path' => $this->rootDir . '/modules/*/lib',
                    'recursive' => true,
                    'prefix' => 1,
                ],
            ], $config['autoload']);
        }

        return $config;
    }

    /**
     * Connects the current plugin's tests to the "test:*" tasks.
     *
     * No-op in standalone mode — test tasks are Symfony CLI only.
     */
    public function connectTests()
    {
        // No-op in standalone mode
    }

    /**
     * Listens for the "task.test.filter_test_files" event.
     *
     * No-op in standalone mode — test tasks are Symfony CLI only.
     *
     * @param sfEvent $event
     * @param array   $files
     *
     * @return array
     */
    public function filterTestFiles(sfEvent $event, $files)
    {
        return is_array($files) ? $files : [];
    }

    /**
     * Guesses the plugin root directory.
     *
     * @return string
     */
    protected function guessRootDir()
    {
        $r = new \ReflectionClass(get_class($this));

        return realpath(dirname($r->getFileName()) . '/..');
    }

    /**
     * Guesses the plugin name.
     *
     * Strips 'Configuration' suffix (13 chars) from the class name.
     *
     * @return string
     */
    protected function guessName()
    {
        return substr(get_class($this), 0, -13);
    }
}
