<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import physical objects from CSV.
 *
 * Delegates to: php symfony csv:physicalobject-import
 */
class CsvPhysicalObjectImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-physical-object';
    protected string $description = 'Import physical objects from CSV';
    protected string $symfonyTask = 'csv:physicalobject-import';
}
