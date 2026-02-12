<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Bulk export multiple XML files at once.
 *
 * Delegates to: php symfony export:bulk
 */
class BulkExportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'export:bulk';
    protected string $description = 'Bulk export multiple XML files at once';
    protected string $symfonyTask = 'export:bulk';
}
