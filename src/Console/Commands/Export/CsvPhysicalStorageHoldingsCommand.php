<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Export physical storage holdings report as CSV data.
 *
 * Delegates to: php symfony csv:physicalstorage-holdings
 */
class CsvPhysicalStorageHoldingsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'csv:physicalstorage-holdings';
    protected string $description = 'Export physical storage holdings report as CSV data';
    protected string $symfonyTask = 'csv:physicalstorage-holdings';
}
