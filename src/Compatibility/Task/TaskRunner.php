<?php

/**
 * Standalone Task Runner — replaces `php symfony` CLI entry point.
 *
 * Discovers all plugin task files, parses argv, instantiates the
 * matching task class, and calls run() with parsed arguments/options.
 *
 * Usage:
 *   php bin/heratio-cli <namespace>:<task> [--option=value] [--flag] [argument]
 *
 * Examples:
 *   php bin/heratio-cli ai:ner-extract --slug=test-fonds
 *   php bin/heratio-cli preservation:fixity --all
 *   php bin/heratio-cli display:auto-detect --force
 *   php bin/heratio-cli ingest:commit --job-id=123
 */

namespace AtomFramework\Compatibility\Task;

class TaskRunner
{
    private string $rootDir;
    private string $pluginsDir;
    private array $tasks = [];
    private array $deferredFiles = [];

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
        $this->pluginsDir = $rootDir . '/atom-ahg-plugins';
    }

    /**
     * Main entry point. Parse argv and dispatch.
     */
    public function run(array $argv): int
    {
        // Remove script name
        array_shift($argv);

        if (empty($argv) || in_array($argv[0], ['--help', '-h', 'help', 'list'])) {
            return $this->showHelp();
        }

        $taskName = array_shift($argv);

        // Handle cc (clear cache) shortcut
        if ('cc' === $taskName || 'cache:clear' === $taskName) {
            return $this->clearCache();
        }

        // Boot compatibility layer
        $this->bootCompatibility();

        // Discover and load tasks
        $this->discoverTasks();

        // Find matching task
        $taskClass = $this->findTask($taskName);
        if (!$taskClass) {
            echo "Task '{$taskName}' not found.\n\n";
            echo "Available tasks matching '" . explode(':', $taskName)[0] . "':\n";
            $this->showMatchingTasks(explode(':', $taskName)[0]);
            return 1;
        }

        // Parse options and arguments
        [$arguments, $options] = $this->parseArgs($argv, $taskClass);

        // Run
        return $taskClass->run($arguments, $options);
    }

    /**
     * Boot the compatibility layer (stubs + DB).
     */
    private function bootCompatibility(): void
    {
        // 1. Ensure Composer autoload is available (PSR-4 for framework namespaces)
        $vendorPaths = [
            dirname(__DIR__, 3) . '/vendor/autoload.php',
            $this->rootDir . '/vendor/autoload.php',
        ];
        foreach ($vendorPaths as $vendorAutoload) {
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
                break;
            }
        }

        // 2. Load task stubs (no external deps — safe to load first)
        require_once __DIR__ . '/task_autoload.php';

        // 3. Load main compatibility stubs (Qubit classes, sfConfig, etc.)
        //    Depends on Composer autoload for QubitModelTrait namespace resolution
        $autoloadFile = dirname(__DIR__) . '/autoload.php';
        if (file_exists($autoloadFile)) {
            require_once $autoloadFile;
        }

        // 4. Load form stubs (some tasks use sfForm for validation)
        $formAutoload = dirname(__DIR__) . '/Form/form_autoload.php';
        if (file_exists($formAutoload)) {
            require_once $formAutoload;
        }

        // 5. Set sfConfig root paths
        if (class_exists('sfConfig', false)) {
            \sfConfig::set('sf_root_dir', $this->rootDir);
            \sfConfig::set('sf_plugins_dir', $this->rootDir . '/plugins');
            \sfConfig::set('sf_upload_dir', $this->rootDir . '/uploads');
            \sfConfig::set('sf_data_dir', $this->rootDir . '/data');
            \sfConfig::set('sf_cache_dir', $this->rootDir . '/cache');
            \sfConfig::set('sf_log_dir', $this->rootDir . '/log');
            \sfConfig::set('sf_web_dir', $this->rootDir);
        }
    }

    /**
     * Discover all task files in plugin directories.
     */
    private function discoverTasks(): void
    {
        if (!is_dir($this->pluginsDir)) {
            return;
        }

        $taskFiles = glob($this->pluginsDir . '/*/lib/task/*Task.class.php');
        $taskFilesAlt = glob($this->pluginsDir . '/*/lib/Task/*Task.class.php');
        $allFiles = array_merge($taskFiles ?: [], $taskFilesAlt ?: []);

        foreach ($allFiles as $file) {
            $this->loadTaskFile($file);
        }

        // Retry deferred files (dependencies should now be loaded)
        $retries = 3;
        while (!empty($this->deferredFiles) && $retries-- > 0) {
            $pending = $this->deferredFiles;
            $this->deferredFiles = [];
            foreach ($pending as $file) {
                $this->loadTaskFile($file);
            }
        }
    }

    /**
     * Load a task file and register the task class.
     */
    private function loadTaskFile(string $file): void
    {
        // Extract class name from filename: fooBarTask.class.php → fooBarTask
        $basename = basename($file, '.class.php');

        // Skip if already loaded
        if (class_exists($basename, false)) {
            $this->registerTask($basename);
            return;
        }

        try {
            require_once $file;
        } catch (\Throwable $e) {
            // Dependency missing (e.g., abstract parent not loaded yet)
            $this->deferredFiles[] = $file;
            return;
        }

        if (!class_exists($basename, false)) {
            return;
        }

        $this->registerTask($basename);
    }

    /**
     * Try to instantiate and register a task class.
     */
    private function registerTask(string $className): void
    {
        try {
            $ref = new \ReflectionClass($className);
            if ($ref->isAbstract()) {
                return;
            }
            $task = new $className($this->getDispatcher(), $this->getFormatter());
            $fullName = $task->getFullName();
            if ($fullName) {
                $this->tasks[$fullName] = $task;
            }
        } catch (\Throwable $e) {
            // Task failed to instantiate — skip silently
        }
    }

    /**
     * Find a task by name (namespace:task).
     */
    private function findTask(string $name): ?\sfBaseTask
    {
        // Exact match
        if (isset($this->tasks[$name])) {
            return $this->tasks[$name];
        }

        // Try case-insensitive
        $lower = strtolower($name);
        foreach ($this->tasks as $fullName => $task) {
            if (strtolower($fullName) === $lower) {
                return $task;
            }
        }

        // Try partial match (unique prefix)
        $matches = [];
        foreach ($this->tasks as $fullName => $task) {
            if (str_starts_with(strtolower($fullName), $lower)) {
                $matches[$fullName] = $task;
            }
        }
        if (1 === count($matches)) {
            return reset($matches);
        }

        return null;
    }

    /**
     * Parse CLI arguments into [arguments, options] arrays.
     */
    private function parseArgs(array $argv, \sfBaseTask $task): array
    {
        $options = [];
        $arguments = [];
        $argDefs = $task->getArguments();
        $argIndex = 0;

        // Set option defaults
        foreach ($task->getOptions() as $opt) {
            if ($opt instanceof \sfCommandOption) {
                $default = $opt->getDefault();
                if (\sfCommandOption::PARAMETER_NONE === ($opt->mode & ~\sfCommandOption::IS_ARRAY)) {
                    $options[$opt->getName()] = $default ?? false;
                } else {
                    $options[$opt->getName()] = $default;
                }
            }
        }

        // Set argument defaults
        foreach ($argDefs as $arg) {
            if ($arg instanceof \sfCommandArgument) {
                $arguments[$arg->getName()] = $arg->getDefault();
            }
        }

        // Add standard options
        $options['application'] = $options['application'] ?? 'qubit';
        $options['env'] = $options['env'] ?? 'cli';
        $options['connection'] = $options['connection'] ?? 'propel';

        foreach ($argv as $token) {
            if (str_starts_with($token, '--')) {
                $token = substr($token, 2);
                if (str_contains($token, '=')) {
                    [$key, $value] = explode('=', $token, 2);
                    $options[$key] = $value;
                } else {
                    $options[$token] = true;
                }
            } elseif (str_starts_with($token, '-')) {
                // Short option
                $short = substr($token, 1);
                // Find matching option by shortcut
                foreach ($task->getOptions() as $opt) {
                    if ($opt instanceof \sfCommandOption && $opt->getShortcut() === $short) {
                        $options[$opt->getName()] = true;
                        break;
                    }
                }
            } else {
                // Positional argument
                if (isset($argDefs[$argIndex]) && $argDefs[$argIndex] instanceof \sfCommandArgument) {
                    $arguments[$argDefs[$argIndex]->getName()] = $token;
                } else {
                    $arguments[$argIndex] = $token;
                }
                $argIndex++;
            }
        }

        return [$arguments, $options];
    }

    /**
     * Show available tasks.
     */
    private function showHelp(): int
    {
        echo "Heratio CLI — Standalone task runner\n";
        echo "Usage: php bin/heratio-cli <namespace:task> [options] [arguments]\n\n";

        $this->bootCompatibility();
        $this->discoverTasks();

        if (empty($this->tasks)) {
            echo "No tasks found.\n";
            return 0;
        }

        // Group by namespace
        $grouped = [];
        foreach ($this->tasks as $fullName => $task) {
            $ns = $task->getNamespace() ?: '(global)';
            $grouped[$ns][$fullName] = $task->getBriefDescription();
        }

        ksort($grouped);
        foreach ($grouped as $ns => $tasks) {
            echo "\033[33m{$ns}\033[0m\n";
            ksort($tasks);
            foreach ($tasks as $name => $desc) {
                printf("  \033[32m%-35s\033[0m %s\n", $name, $desc);
            }
            echo "\n";
        }

        return 0;
    }

    /**
     * Show tasks matching a namespace prefix.
     */
    private function showMatchingTasks(string $prefix): void
    {
        $prefix = strtolower($prefix);
        foreach ($this->tasks as $fullName => $task) {
            if (str_starts_with(strtolower($fullName), $prefix) || str_starts_with(strtolower($task->getNamespace()), $prefix)) {
                printf("  \033[32m%-35s\033[0m %s\n", $fullName, $task->getBriefDescription());
            }
        }
    }

    /**
     * Clear cache (replaces `php symfony cc`).
     */
    private function clearCache(): int
    {
        $cacheDir = $this->rootDir . '/cache';
        if (is_dir($cacheDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getRealPath());
                } else {
                    @unlink($file->getRealPath());
                }
            }
        }
        echo "Cache cleared.\n";
        return 0;
    }

    private function getDispatcher(): \sfEventDispatcher
    {
        return new \sfEventDispatcher();
    }

    private function getFormatter(): \sfFormatter
    {
        return new \sfFormatter();
    }
}
