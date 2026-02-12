<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Expire and remove data past retention dates.
 *
 * Delegates to Symfony for complex data expiry logic
 * involving multiple related tables and cascade operations.
 */
class ExpireDataCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:expire-data';
    protected string $description = 'Expire and remove data past retention dates';
    protected string $symfonyTask = 'tools:expire-data';

    protected function configure(): void
    {
        $this->addOption('dry-run', null, 'Show what would be expired without removing');
        $this->addOption('force', 'f', 'Skip confirmation prompt');
    }
}
