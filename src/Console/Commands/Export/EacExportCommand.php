<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Bulk export multiple EAC XML files at once for authority records.
 *
 * Delegates to: php symfony export:auth-recs
 */
class EacExportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'export:auth-recs';
    protected string $description = 'Bulk export multiple EAC XML files at once for authority records';
    protected string $symfonyTask = 'export:auth-recs';
}
