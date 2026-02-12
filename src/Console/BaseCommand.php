<?php

namespace AtomFramework\Console;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Base class for all AtoM Heratio CLI commands.
 *
 * Provides argument/option parsing, output helpers, and database access.
 * Subclasses implement handle() and optionally override configure().
 *
 * Usage:
 *   class MyCommand extends BaseCommand {
 *       protected string $name = 'tools:my-command';
 *       protected string $description = 'Does something useful';
 *
 *       protected function configure(): void {
 *           $this->addArgument('name', 'The name to use', true);
 *           $this->addOption('verbose', 'v', 'Show detailed output');
 *           $this->addOption('limit', 'l', 'Max records', '100');
 *       }
 *
 *       protected function handle(): int {
 *           $name = $this->argument('name');
 *           $limit = (int) $this->option('limit');
 *           $this->info("Processing {$name} with limit {$limit}");
 *           return 0;
 *       }
 *   }
 */
abstract class BaseCommand
{
    /** @var string Command name (e.g. 'tools:add-superuser') */
    protected string $name = '';

    /** @var string Brief description shown in help */
    protected string $description = '';

    /** @var string Detailed description shown in help */
    protected string $detailedDescription = '';

    /** @var array Raw argv passed to the command */
    protected array $argv = [];

    /** @var array Defined argument specs: [{name, description, required}] */
    private array $argumentDefs = [];

    /** @var array Defined option specs: [{name, short, description, default}] */
    private array $optionDefs = [];

    /** @var array Parsed argument values */
    private array $arguments = [];

    /** @var array Parsed option values */
    private array $options = [];

    /** @var bool Whether to show verbose output */
    protected bool $verbose = false;

    /** @var string ATOM_ROOT path */
    protected string $atomRoot;

    public function __construct(array $argv = [])
    {
        $this->argv = $argv;
        $this->atomRoot = defined('ATOM_ROOT') ? ATOM_ROOT : dirname(dirname(dirname(__DIR__)));

        // Built-in options
        $this->addOption('help', 'h', 'Show command help');
        $this->addOption('verbose', 'v', 'Verbose output');
        $this->addOption('no-interaction', 'n', 'Do not ask interactive questions');

        $this->configure();
    }

    /**
     * Override to define arguments and options.
     */
    protected function configure(): void
    {
        // Subclasses override this
    }

    /**
     * Implement the command logic. Return 0 for success, 1+ for failure.
     */
    abstract protected function handle(): int;

    /**
     * Run the command (called by the CLI router).
     */
    public function run(): int
    {
        $this->parseArgv();

        if ($this->option('help')) {
            $this->showHelp();
            return 0;
        }

        $this->verbose = (bool) $this->option('verbose');

        // Validate required arguments
        foreach ($this->argumentDefs as $i => $def) {
            if ($def['required'] && !isset($this->arguments[$def['name']])) {
                $this->error("Missing required argument: {$def['name']}");
                $this->showHelp();
                return 1;
            }
        }

        try {
            return $this->handle();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            if ($this->verbose) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }

    // ─── Argument/Option Definition ──────────────────────────────────

    protected function addArgument(string $name, string $description = '', bool $required = false): void
    {
        $this->argumentDefs[] = [
            'name' => $name,
            'description' => $description,
            'required' => $required,
        ];
    }

    protected function addOption(string $name, ?string $short = null, string $description = '', ?string $default = null): void
    {
        $this->optionDefs[] = [
            'name' => $name,
            'short' => $short,
            'description' => $description,
            'default' => $default,
        ];
    }

    // ─── Argument/Option Access ──────────────────────────────────────

    protected function argument(string $name, ?string $default = null): ?string
    {
        return $this->arguments[$name] ?? $default;
    }

    protected function option(string $name, ?string $default = null): ?string
    {
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }

        // Check defaults
        foreach ($this->optionDefs as $def) {
            if ($def['name'] === $name) {
                return $def['default'] ?? $default;
            }
        }

        return $default;
    }

    protected function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    // ─── Argv Parsing ────────────────────────────────────────────────

    private function parseArgv(): void
    {
        $positionalIndex = 0;
        $args = $this->argv;

        foreach ($args as $arg) {
            // Long option with value: --name=value
            if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
                $parts = explode('=', substr($arg, 2), 2);
                $this->options[$parts[0]] = $parts[1];
                continue;
            }

            // Long option (flag): --verbose
            if (str_starts_with($arg, '--')) {
                $this->options[substr($arg, 2)] = '1';
                continue;
            }

            // Short option: -v or -l=100
            if (str_starts_with($arg, '-') && strlen($arg) > 1 && $arg[1] !== '-') {
                $shortFlag = substr($arg, 1);
                if (str_contains($shortFlag, '=')) {
                    $parts = explode('=', $shortFlag, 2);
                    $longName = $this->resolveShort($parts[0]);
                    $this->options[$longName] = $parts[1];
                } else {
                    $longName = $this->resolveShort($shortFlag);
                    $this->options[$longName] = '1';
                }
                continue;
            }

            // Positional argument
            if (isset($this->argumentDefs[$positionalIndex])) {
                $this->arguments[$this->argumentDefs[$positionalIndex]['name']] = $arg;
                $positionalIndex++;
            }
        }
    }

    private function resolveShort(string $short): string
    {
        foreach ($this->optionDefs as $def) {
            if ($def['short'] === $short) {
                return $def['name'];
            }
        }
        return $short;
    }

    // ─── Output Helpers ──────────────────────────────────────────────

    protected function line(string $text): void
    {
        echo $text . PHP_EOL;
    }

    protected function info(string $text): void
    {
        echo "\033[36m{$text}\033[0m" . PHP_EOL;
    }

    protected function success(string $text): void
    {
        echo "\033[32m✓ {$text}\033[0m" . PHP_EOL;
    }

    protected function warning(string $text): void
    {
        echo "\033[33m⚠ {$text}\033[0m" . PHP_EOL;
    }

    protected function error(string $text): void
    {
        echo "\033[31m✗ {$text}\033[0m" . PHP_EOL;
    }

    protected function comment(string $text): void
    {
        echo "\033[90m{$text}\033[0m" . PHP_EOL;
    }

    protected function bold(string $text): void
    {
        echo "\033[1m{$text}\033[0m" . PHP_EOL;
    }

    protected function newline(): void
    {
        echo PHP_EOL;
    }

    /**
     * Print a table with headers and rows.
     */
    protected function table(array $headers, array $rows): void
    {
        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = mb_strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $cell));
            }
        }

        // Print header
        $headerLine = '';
        $separator = '';
        foreach ($headers as $i => $header) {
            $headerLine .= '  ' . str_pad($header, $widths[$i]);
            $separator .= '  ' . str_repeat('─', $widths[$i]);
        }
        $this->bold($headerLine);
        $this->line($separator);

        // Print rows
        foreach ($rows as $row) {
            $line = '';
            foreach ($row as $i => $cell) {
                $line .= '  ' . str_pad((string) $cell, $widths[$i]);
            }
            $this->line($line);
        }
    }

    /**
     * Ask for user confirmation. Returns true if confirmed.
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        if ($this->option('no-interaction')) {
            return $default;
        }

        $hint = $default ? '[Y/n]' : '[y/N]';
        echo "{$question} {$hint} ";
        $answer = strtolower(trim(fgets(STDIN) ?: ''));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes']);
    }

    /**
     * Ask for user input.
     */
    protected function ask(string $question, ?string $default = null): string
    {
        if ($this->option('no-interaction')) {
            return $default ?? '';
        }

        $hint = $default !== null ? " [{$default}]" : '';
        echo "{$question}{$hint}: ";
        $answer = trim(fgets(STDIN) ?: '');

        return $answer !== '' ? $answer : ($default ?? '');
    }

    /**
     * Show a progress indicator for iterating over items.
     */
    protected function withProgress(iterable $items, callable $callback, ?int $total = null): void
    {
        $count = 0;
        $total = $total ?? (is_countable($items) ? count($items) : null);

        foreach ($items as $key => $item) {
            $count++;
            $progress = $total ? " ({$count}/{$total})" : " ({$count})";
            echo "\r\033[K  Processing{$progress}...";
            $callback($item, $key);
        }

        echo "\r\033[K";
    }

    // ─── Help ────────────────────────────────────────────────────────

    protected function showHelp(): void
    {
        $this->newline();
        $this->bold("  {$this->name}");
        if ($this->description) {
            $this->line("  {$this->description}");
        }
        $this->newline();

        // Usage
        $usage = "  php bin/atom {$this->name}";
        foreach ($this->argumentDefs as $def) {
            $usage .= $def['required'] ? " <{$def['name']}>" : " [{$def['name']}]";
        }
        $usage .= ' [options]';
        $this->info("Usage:");
        $this->line($usage);

        // Arguments
        if (!empty($this->argumentDefs)) {
            $this->newline();
            $this->info("Arguments:");
            foreach ($this->argumentDefs as $def) {
                $req = $def['required'] ? ' (required)' : '';
                $this->line("  {$def['name']}{$req}  {$def['description']}");
            }
        }

        // Options
        $this->newline();
        $this->info("Options:");
        foreach ($this->optionDefs as $def) {
            $short = $def['short'] ? "-{$def['short']}, " : '    ';
            $default = $def['default'] !== null ? " [default: {$def['default']}]" : '';
            $this->line("  {$short}--{$def['name']}{$default}  {$def['description']}");
        }

        if ($this->detailedDescription) {
            $this->newline();
            $this->info("Description:");
            $this->line("  {$this->detailedDescription}");
        }

        $this->newline();
    }

    // ─── Utility ─────────────────────────────────────────────────────

    /**
     * Get the path to the AtoM root directory.
     */
    protected function getAtomRoot(): string
    {
        return $this->atomRoot;
    }

    /**
     * Get the path to the framework directory.
     */
    protected function getFrameworkRoot(): string
    {
        return $this->atomRoot . '/atom-framework';
    }

    /**
     * Get the path to the AHG plugins directory.
     */
    protected function getPluginsRoot(): string
    {
        return $this->atomRoot . '/atom-ahg-plugins';
    }

    /**
     * Execute a shell command and return [exitCode, output].
     */
    protected function exec(string $command): array
    {
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        return [$exitCode, implode("\n", $output)];
    }

    /**
     * Execute and stream output to console.
     */
    protected function passthru(string $command): int
    {
        $exitCode = 0;
        passthru($command . ' 2>&1', $exitCode);
        return $exitCode;
    }

    /**
     * Get the command name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the command description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the namespace portion of the command name.
     */
    public function getNamespace(): string
    {
        $parts = explode(':', $this->name, 2);
        return $parts[0];
    }
}
