<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Clear AtoM jobs.
 *
 * Delegates to: php symfony jobs:clear
 */
class ClearJobsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'jobs:clear';
    protected string $description = 'Clear AtoM jobs';
    protected string $symfonyTask = 'jobs:clear';
}
