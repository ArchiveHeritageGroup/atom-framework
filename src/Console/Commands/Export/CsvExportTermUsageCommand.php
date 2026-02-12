<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Export terms associated with information objects as CSV file(s).
 *
 * Delegates to: php symfony csv:export-term-usage
 */
class CsvExportTermUsageCommand extends SymfonyBridgeCommand
{
    protected string $name = 'csv:export-term-usage';
    protected string $description = 'Export terms associated with information objects as CSV file(s)';
    protected string $symfonyTask = 'csv:export-term-usage';
}
