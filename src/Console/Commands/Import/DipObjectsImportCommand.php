<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import digital objects from Archivematica DIP using CSV file.
 *
 * Delegates to: php symfony import:dip-objects
 */
class DipObjectsImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:dip-objects';
    protected string $description = 'Import DIP objects from Archivematica';
}
