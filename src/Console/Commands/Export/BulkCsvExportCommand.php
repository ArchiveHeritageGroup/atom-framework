<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Bulk export multiple CSV files at once.
 *
 * Delegates to: php symfony export:bulk-csv
 */
class BulkCsvExportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'export:bulk-csv';
    protected string $description = 'Bulk export multiple CSV files at once';
    protected string $symfonyTask = 'export:bulk-csv';
}
