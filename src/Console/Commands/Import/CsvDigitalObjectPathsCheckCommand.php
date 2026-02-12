<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Check digital object paths in CSV data.
 *
 * Delegates to: php symfony csv:digital-object-path-check
 */
class CsvDigitalObjectPathsCheckCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-digital-object-paths-check';
    protected string $description = 'Check digital object paths in CSV';
    protected string $symfonyTask = 'csv:digital-object-path-check';
}
