<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Run custom logic scripts.
 *
 * Delegates to Symfony for execution of custom logic that may
 * depend on Propel models and the full Symfony runtime.
 */
class RunCustomLogicCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:run-custom-logic';
    protected string $description = 'Run custom logic scripts';
    protected string $symfonyTask = 'tools:run-custom-logic';

    protected function configure(): void
    {
        $this->addArgument('script', 'The script identifier or path to run');
    }
}
