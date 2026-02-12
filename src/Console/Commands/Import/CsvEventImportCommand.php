<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import events from CSV.
 *
 * Delegates to: php symfony csv:event-import
 */
class CsvEventImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-event';
    protected string $description = 'Import events from CSV';
    protected string $symfonyTask = 'csv:event-import';
}
