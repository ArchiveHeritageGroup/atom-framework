<?php

/**
 * sfSimpleAutoload stub for standalone Heratio mode.
 *
 * Provides the sfSimpleAutoload singleton when Symfony is not installed.
 * Required because sfPluginConfiguration::initializeAutoload() calls
 * sfSimpleAutoload::getInstance() and ->addDirectory() / ->register().
 *
 * API-compatible with vendor/symfony/lib/autoload/sfSimpleAutoload.class.php.
 * In dual-stack mode (Symfony present), the real sfSimpleAutoload is loaded
 * by sfCoreAutoload and this file is never included.
 *
 * Unlike the real implementation, this stub does NOT use sfFinder (which
 * is unavailable in standalone mode). Instead, it uses PHP's built-in
 * RecursiveDirectoryIterator for directory scanning.
 */
class sfSimpleAutoload
{
    protected static $registered = false;
    protected static $instance = null;

    protected $cacheFile = null;
    protected $dirs = [];
    protected $files = [];
    protected $classes = [];
    protected $overriden = [];

    protected function __construct($cacheFile = null)
    {
        // No cache file operations in standalone mode
        $this->cacheFile = $cacheFile;
    }

    /**
     * Retrieves the singleton instance.
     *
     * @param string|null $cacheFile The file path to save the cache (ignored in stub)
     *
     * @return sfSimpleAutoload
     */
    public static function getInstance($cacheFile = null)
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($cacheFile);
        }

        return self::$instance;
    }

    /**
     * Register sfSimpleAutoload in spl autoloader.
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }

        ini_set('unserialize_callback_func', 'spl_autoload_call');
        spl_autoload_register([self::getInstance(), 'autoload']);
        self::$registered = true;
    }

    /**
     * Unregister sfSimpleAutoload from spl autoloader.
     */
    public static function unregister()
    {
        spl_autoload_unregister([self::getInstance(), 'autoload']);
        self::$registered = false;
    }

    /**
     * Handles autoloading of classes.
     *
     * @param string $class A class name
     *
     * @return bool Returns true if the class has been loaded
     */
    public function autoload($class)
    {
        $class = strtolower($class);

        if (class_exists($class, false) || interface_exists($class, false)) {
            return true;
        }

        if (isset($this->classes[$class])) {
            try {
                require $this->classes[$class];
            } catch (\Exception $e) {
                // Silently continue — non-fatal in standalone mode
            }

            return true;
        }

        return false;
    }

    /**
     * Adds a directory to the autoloading system.
     *
     * Uses RecursiveDirectoryIterator instead of sfFinder (unavailable
     * in standalone mode).
     *
     * @param string $dir The directory to look for classes
     * @param string $ext The extension to look for
     */
    public function addDirectory($dir, $ext = '.php')
    {
        $dirs = glob($dir);
        if (!$dirs) {
            return;
        }

        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                continue;
            }

            $this->dirs[] = $d;

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($d, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && str_ends_with($file->getFilename(), $ext)) {
                        $this->addFile($file->getPathname(), false);
                    }
                }
            } catch (\Exception $e) {
                // Non-fatal — continue
            }
        }
    }

    /**
     * Adds a file to the autoloading system.
     *
     * @param string $file     A file path
     * @param bool   $register Whether to register the file as a single entity
     */
    public function addFile($file, $register = true)
    {
        if (!is_file($file)) {
            return;
        }

        if ($register) {
            $this->files[] = $file;
        }

        // Extract class/interface names from the file
        $contents = file_get_contents($file);
        if (false === $contents) {
            return;
        }

        preg_match_all('~^\s*(?:abstract\s+|final\s+)?(?:class|interface)\s+(\w+)~mi', $contents, $classes);
        foreach ($classes[1] as $class) {
            $this->classes[strtolower($class)] = $file;
        }
    }

    /**
     * Adds files to the autoloading system.
     *
     * @param array $files    An array of files
     * @param bool  $register Whether to register those files
     */
    public function addFiles(array $files, $register = true)
    {
        foreach ($files as $file) {
            $this->addFile($file, $register);
        }
    }

    /**
     * Sets the path for a particular class.
     *
     * @param string $class A PHP class name
     * @param string $path  An absolute path
     */
    public function setClassPath($class, $path)
    {
        $class = strtolower($class);
        $this->overriden[$class] = $path;
        $this->classes[$class] = $path;
    }

    /**
     * Returns the path where a particular class can be found.
     *
     * @param string $class A PHP class name
     *
     * @return string|null An absolute path
     */
    public function getClassPath($class)
    {
        $class = strtolower($class);

        return $this->classes[$class] ?? null;
    }

    /**
     * Loads configuration from the supplied files.
     *
     * No-op in standalone mode — sfAutoloadConfigHandler is unavailable.
     *
     * @param array $files An array of autoload.yml files
     */
    public function loadConfiguration(array $files)
    {
        // No-op — sfAutoloadConfigHandler requires full Symfony
    }

    /**
     * Saves the cache — no-op in standalone mode.
     */
    public function saveCache()
    {
        // No-op
    }

    /**
     * Loads the cache — no-op in standalone mode.
     */
    public function loadCache()
    {
        // No-op
    }

    /**
     * Removes the cache — no-op in standalone mode.
     */
    public function removeCache()
    {
        // No-op
    }

    /**
     * Reloads the autoloader.
     */
    public function reload()
    {
        $this->classes = [];

        foreach ($this->dirs as $dir) {
            $this->addDirectory($dir);
        }

        foreach ($this->files as $file) {
            $this->addFile($file);
        }

        foreach ($this->overriden as $class => $path) {
            $this->classes[$class] = $path;
        }
    }
}
