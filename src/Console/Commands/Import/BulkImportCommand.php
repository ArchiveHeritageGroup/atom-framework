<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Bulk import multiple XML/CSV files at once.
 *
 * Delegates to: php symfony import:bulk
 */
class BulkImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:bulk';
    protected string $description = 'Bulk XML import';
}
