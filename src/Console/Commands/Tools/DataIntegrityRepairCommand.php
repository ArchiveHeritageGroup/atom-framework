<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Check and repair data integrity issues.
 *
 * Delegates to Symfony for complex integrity checks spanning
 * nested sets, foreign keys, and Propel object validation.
 */
class DataIntegrityRepairCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:data-integrity-repair';
    protected string $description = 'Check and repair data integrity issues';
    protected string $symfonyTask = 'tools:data-integrity-repair';

    protected function configure(): void
    {
        $this->addOption('dry-run', null, 'Show issues without repairing');
        $this->addOption('force', 'f', 'Skip confirmation prompt');
    }
}
