<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Custom CSV import using user-defined criteria.
 *
 * Delegates to: php symfony csv:custom-import
 */
class CsvCustomImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-custom';
    protected string $description = 'Custom CSV import';
    protected string $symfonyTask = 'csv:custom-import';
}
