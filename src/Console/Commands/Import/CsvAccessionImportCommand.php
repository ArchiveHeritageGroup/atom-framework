<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import accessions from CSV.
 *
 * Delegates to: php symfony csv:accession-import
 */
class CsvAccessionImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-accession';
    protected string $description = 'Import accessions from CSV';
    protected string $symfonyTask = 'csv:accession-import';
}
