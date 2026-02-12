<?php

namespace AtomFramework\Console;

/**
 * Base command for tasks that still need Symfony/Propel under the hood.
 *
 * Delegates execution to the old `php symfony namespace:command` process.
 * This allows all commands to be discoverable via `php bin/atom` while
 * complex Propel-dependent tasks keep working.
 *
 * Over time, bridge commands should be replaced with native BaseCommand
 * implementations using Laravel Query Builder.
 */
abstract class SymfonyBridgeCommand extends BaseCommand
{
    /** @var string The original Symfony task name (e.g. 'search:populate') */
    protected string $symfonyTask = '';

    protected function handle(): int
    {
        $task = $this->symfonyTask ?: $this->name;
        $args = $this->buildSymfonyArgs();
        $cmd = sprintf('php %s/symfony %s %s', escapeshellarg($this->atomRoot), escapeshellarg($task), $args);

        if ($this->verbose) {
            $this->comment("  Delegating to: {$cmd}");
        }

        return $this->passthru($cmd);
    }

    /**
     * Build CLI arguments string from parsed options and arguments.
     */
    protected function buildSymfonyArgs(): string
    {
        $parts = [];

        // Pass through positional arguments
        foreach ($this->argv as $arg) {
            if (!str_starts_with($arg, '-')) {
                $parts[] = escapeshellarg($arg);
            }
        }

        // Pass through options
        foreach ($this->argv as $arg) {
            if (str_starts_with($arg, '-')) {
                // Skip our internal options
                if (in_array($arg, ['--verbose', '-v', '--help', '-h'])) {
                    continue;
                }
                $parts[] = $arg;
            }
        }

        return implode(' ', $parts);
    }
}
