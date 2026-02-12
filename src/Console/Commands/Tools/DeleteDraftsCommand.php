<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Delete all draft descriptions.
 *
 * Delegates to Symfony for complex publication status queries
 * and nested set cleanup that require Propel.
 */
class DeleteDraftsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:delete-drafts';
    protected string $description = 'Delete all draft archival descriptions';
    protected string $symfonyTask = 'tools:delete-drafts';

    protected function configure(): void
    {
        $this->addOption('force', 'f', 'Skip confirmation prompt');
        $this->addOption('repository', 'r', 'Limit to a specific repository slug');
    }
}
