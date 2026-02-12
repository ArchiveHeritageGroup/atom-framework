<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Export repository information to a CSV.
 *
 * Delegates to: php symfony csv:repository-export
 */
class CsvRepositoryExportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'csv:repository-export';
    protected string $description = 'Export repository information to a CSV';
    protected string $symfonyTask = 'csv:repository-export';
}
