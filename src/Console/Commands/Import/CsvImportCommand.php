<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import CSV information object data.
 *
 * Delegates to: php symfony csv:import
 */
class CsvImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv';
    protected string $description = 'Import CSV data';
    protected string $symfonyTask = 'csv:import';
}
