<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Gearman worker daemon.
 *
 * Delegates to: php symfony jobs:worker
 */
class JobWorkerCommand extends SymfonyBridgeCommand
{
    protected string $name = 'jobs:worker';
    protected string $description = 'Gearman worker daemon';
    protected string $symfonyTask = 'jobs:worker';
}
