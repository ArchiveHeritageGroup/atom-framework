<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Delete a description and its descendants.
 *
 * Delegates to Symfony for complex nested set (MPTT) operations
 * that require Propel object hydration and cascade logic.
 */
class DeleteDescriptionCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:delete-description';
    protected string $description = 'Delete an archival description and its descendants';
    protected string $symfonyTask = 'tools:delete-description';

    protected function configure(): void
    {
        $this->addArgument('slug', 'The slug of the description to delete');
        $this->addOption('force', 'f', 'Skip confirmation prompt');
        $this->addOption('no-confirm', null, 'Alias for --force');
    }
}
