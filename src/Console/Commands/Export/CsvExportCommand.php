<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Export descriptions as CSV file(s).
 *
 * Delegates to: php symfony csv:export
 */
class CsvExportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'csv:export';
    protected string $description = 'Export descriptions as CSV file(s)';
    protected string $symfonyTask = 'csv:export';
}
