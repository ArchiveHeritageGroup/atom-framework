<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Check CSV import file, providing diagnostic info.
 *
 * Delegates to: php symfony csv:check-import
 */
class CsvCheckImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-check';
    protected string $description = 'Check CSV import file';
    protected string $symfonyTask = 'csv:check-import';
}
