<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Delete data created by an import.
 *
 * Delegates to: php symfony import:delete
 */
class DeleteImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:delete';
    protected string $description = 'Delete imported records';
}
