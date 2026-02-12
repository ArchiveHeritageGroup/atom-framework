<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import repositories from CSV.
 *
 * Delegates to: php symfony csv:repository-import
 */
class CsvRepositoryImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-repository';
    protected string $description = 'Import repositories from CSV';
    protected string $symfonyTask = 'csv:repository-import';
}
