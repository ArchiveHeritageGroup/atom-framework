<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import authority records from CSV.
 *
 * Delegates to: php symfony csv:authority-import
 */
class CsvAuthorityRecordImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-authority-record';
    protected string $description = 'Import authority records from CSV';
    protected string $symfonyTask = 'csv:authority-import';
}
