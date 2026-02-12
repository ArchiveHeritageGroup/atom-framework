<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Export accession record data to a CSV file.
 *
 * Delegates to: php symfony csv:accession-export
 */
class CsvAccessionExportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'csv:accession-export';
    protected string $description = 'Export accession record data to a CSV file';
    protected string $symfonyTask = 'csv:accession-export';
}
