<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Run ad-hoc PHP logic contained in a file.
 *
 * Ported from lib/task/tools/runCustomLogicTask.class.php.
 * Boots Propel so that included scripts can use Qubit model classes.
 */
class RunCustomLogicCommand extends BaseCommand
{
    protected string $name = 'tools:run-custom-logic';
    protected string $description = 'Run ad-hoc logic contained in a PHP file';
    protected string $detailedDescription = <<<'EOF'
Run ad-hoc logic contained in a PHP file.

The specified PHP file will be included with full access to Propel models
and the AtoM framework. This is useful for one-off data migrations,
bulk updates, or debugging scripts.

Examples:
    php bin/atom tools:run-custom-logic /path/to/script.php
    php bin/atom tools:run-custom-logic /path/to/script.php --log
    php bin/atom tools:run-custom-logic /path/to/script.php --log --log-file=/var/log/custom.log
EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The PHP file containing custom logic to run', true);
        $this->addOption('log', null, 'Log execution of PHP file');
        $this->addOption('log-file', null, 'File to log to', $this->atomRoot . '/log/tools_run.log');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $filename = $this->argument('filename');

        if (!file_exists($filename) || false === $fh = fopen($filename, 'rb')) {
            throw new \RuntimeException('You must specify a valid filename');
        }
        fclose($fh);

        $this->info(sprintf('Running: %s', $filename));

        include $filename;

        // Optionally log script execution
        if ($this->hasOption('log')) {
            $logFile = $this->option('log-file', $this->atomRoot . '/log/tools_run.log');
            $logDir = dirname($logFile);

            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $timestamp = date('Y-m-d H:i:s');
            file_put_contents(
                $logFile,
                "[{$timestamp}] Executed: {$filename}\n",
                FILE_APPEND | LOCK_EX
            );

            $this->info(sprintf('Logged execution to: %s', $logFile));
        }

        $this->success('Done.');

        return 0;
    }
}
