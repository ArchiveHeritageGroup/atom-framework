<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Export authority record data as CSV file(s).
 *
 * Delegates to: php symfony csv:authority-export
 */
class CsvAuthorityExportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'csv:authority-export';
    protected string $description = 'Export authority record data as CSV file(s)';
    protected string $symfonyTask = 'csv:authority-export';
}
