<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Purge all data from AtoM.
 *
 * Delegates to Symfony for the destructive purge operation that
 * truncates tables and rebuilds the schema via Propel.
 */
class PurgeCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:purge';
    protected string $description = 'Purge all data from AtoM (DESTRUCTIVE)';
    protected string $symfonyTask = 'tools:purge';

    protected function configure(): void
    {
        $this->addOption('demo', null, 'Reload demo/sample data after purging');
        $this->addOption('no-confirmation', null, 'Skip confirmation prompts');
    }
}
