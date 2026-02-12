<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import deaccessions from CSV.
 *
 * Delegates to: php symfony csv:deaccession-import
 */
class CsvDeaccessionImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-deaccession';
    protected string $description = 'Import deaccessions from CSV';
    protected string $symfonyTask = 'csv:deaccession-import';
}
