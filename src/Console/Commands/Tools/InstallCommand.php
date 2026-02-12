<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Run the AtoM installation process.
 *
 * Delegates to Symfony for the complex installation procedure
 * including database setup, Elasticsearch indexing, and Propel schema.
 */
class InstallCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:install';
    protected string $description = 'Run the AtoM installation process';
    protected string $symfonyTask = 'tools:install';

    protected function configure(): void
    {
        $this->addOption('demo', null, 'Install with demo/sample data');
        $this->addOption('no-confirmation', null, 'Skip confirmation prompts');
    }
}
