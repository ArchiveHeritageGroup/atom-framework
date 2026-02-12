<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Find and set latitude/longitude for repositories.
 *
 * Delegates to Symfony for geocoding operations that use
 * external API calls and Propel object updates.
 */
class FindRepositoryLatLngCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:find-repository-latlng';
    protected string $description = 'Find and set latitude/longitude for repositories via geocoding';
    protected string $symfonyTask = 'tools:find-repository-latlng';

    protected function configure(): void
    {
        $this->addOption('all', null, 'Process all repositories (not just those missing coordinates)');
        $this->addOption('dry-run', null, 'Show results without saving');
    }
}
