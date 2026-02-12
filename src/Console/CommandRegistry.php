<?php

namespace AtomFramework\Console;

/**
 * Auto-discovers and registers CLI commands from framework and plugin directories.
 *
 * Scans:
 *   1. atom-framework/src/Console/Commands/ (recursive, framework commands)
 *   2. atom-ahg-plugins/{plugin}/lib/Commands/ (plugin commands)
 *
 * Each command class must extend BaseCommand and define a $name property.
 */
class CommandRegistry
{
    /** @var array<string, BaseCommand> name => instance */
    private array $commands = [];

    /** @var array<string, string> name => class file path */
    private array $commandFiles = [];

    private string $atomRoot;

    public function __construct(?string $atomRoot = null)
    {
        $this->atomRoot = $atomRoot ?? (defined('ATOM_ROOT') ? ATOM_ROOT : dirname(dirname(dirname(__DIR__))));
    }

    /**
     * Discover all command classes from known directories.
     */
    public function discover(): void
    {
        $this->discoverFrameworkCommands();
        $this->discoverPluginCommands();
    }

    /**
     * Get a command instance by name (lazy-loaded).
     */
    public function get(string $name, array $argv = []): ?BaseCommand
    {
        // Already instantiated
        if (isset($this->commands[$name])) {
            return $this->commands[$name];
        }

        // Lazy load from discovered file
        if (isset($this->commandFiles[$name])) {
            require_once $this->commandFiles[$name];
            $classes = $this->getClassesFromFile($this->commandFiles[$name]);
            foreach ($classes as $class) {
                if (is_subclass_of($class, BaseCommand::class)) {
                    $instance = new $class($argv);
                    if ($instance->getName() === $name) {
                        $this->commands[$name] = $instance;
                        return $instance;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get all discovered command names grouped by namespace.
     *
     * @return array<string, array<string, string>> namespace => [name => description]
     */
    public function all(): array
    {
        $grouped = [];

        foreach ($this->commandFiles as $name => $file) {
            $parts = explode(':', $name, 2);
            $ns = $parts[0] ?? 'other';

            // Lazy-load to get description
            if (!isset($this->commands[$name])) {
                require_once $file;
                $classes = $this->getClassesFromFile($file);
                foreach ($classes as $class) {
                    if (is_subclass_of($class, BaseCommand::class)) {
                        $instance = new $class();
                        if ($instance->getName() === $name) {
                            $this->commands[$name] = $instance;
                            break;
                        }
                    }
                }
            }

            $description = isset($this->commands[$name]) ? $this->commands[$name]->getDescription() : '';
            $grouped[$ns][$name] = $description;
        }

        ksort($grouped);
        return $grouped;
    }

    /**
     * Check if a command exists.
     */
    public function has(string $name): bool
    {
        return isset($this->commandFiles[$name]);
    }

    /**
     * Get all command names in a given namespace.
     */
    public function inNamespace(string $namespace): array
    {
        $result = [];
        foreach ($this->commandFiles as $name => $file) {
            if (str_starts_with($name, $namespace . ':')) {
                $result[] = $name;
            }
        }
        return $result;
    }

    /**
     * Get count of discovered commands.
     */
    public function count(): int
    {
        return count($this->commandFiles);
    }

    // ─── Discovery ───────────────────────────────────────────────────

    private function discoverFrameworkCommands(): void
    {
        $dir = $this->atomRoot . '/atom-framework/src/Console/Commands';
        if (!is_dir($dir)) {
            return;
        }

        $this->scanDirectory($dir);
    }

    private function discoverPluginCommands(): void
    {
        $dir = $this->atomRoot . '/atom-ahg-plugins';
        if (!is_dir($dir)) {
            return;
        }

        $plugins = glob($dir . '/ahg*', GLOB_ONLYDIR);
        foreach ($plugins as $pluginPath) {
            $commandsDir = $pluginPath . '/lib/Commands';
            if (is_dir($commandsDir)) {
                $this->scanDirectory($commandsDir);
            }
        }
    }

    private function scanDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $name = $this->extractCommandName($path);
            if ($name !== null) {
                $this->commandFiles[$name] = $path;
            }
        }
    }

    /**
     * Extract the command name from a PHP file without requiring/instantiating it.
     * Reads the file and looks for: protected string $name = 'xxx:yyy';
     */
    private function extractCommandName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Match: protected string $name = 'namespace:command';
        if (preg_match('/protected\s+string\s+\$name\s*=\s*[\'"]([a-z0-9\-]+:[a-z0-9\-]+)[\'"]\s*;/i', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get fully qualified class names from a file.
     */
    private function getClassesFromFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $m)) {
            $namespace = $m[1] . '\\';
        }

        $classes = [];
        if (preg_match_all('/class\s+(\w+)/', $content, $m)) {
            foreach ($m[1] as $class) {
                $classes[] = $namespace . $class;
            }
        }

        return $classes;
    }
}
