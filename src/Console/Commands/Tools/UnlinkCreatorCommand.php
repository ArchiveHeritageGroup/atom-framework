<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Unlink a creator from archival descriptions.
 *
 * Delegates to Symfony for complex relation operations involving
 * Propel object graphs and event-based cascade logic.
 */
class UnlinkCreatorCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:unlink-creator';
    protected string $description = 'Unlink a creator (actor) from archival descriptions';
    protected string $symfonyTask = 'tools:unlink-creator';

    protected function configure(): void
    {
        $this->addArgument('slug', 'The slug of the creator to unlink');
        $this->addOption('force', 'f', 'Skip confirmation prompt');
    }
}
