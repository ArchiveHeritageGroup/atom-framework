<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Update publication status of archival descriptions.
 *
 * Delegates to Symfony for complex publication status updates
 * involving nested set traversal and Propel object saves.
 */
class UpdatePublicationStatusCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:update-publication-status';
    protected string $description = 'Update publication status of archival descriptions';
    protected string $symfonyTask = 'tools:update-publication-status';

    protected function configure(): void
    {
        $this->addArgument('slug', 'The slug of the description (or "all")');
        $this->addOption('status', 's', 'New status: published or draft');
        $this->addOption('recursive', null, 'Apply to descendants as well');
        $this->addOption('force', 'f', 'Skip confirmation prompt');
    }
}
