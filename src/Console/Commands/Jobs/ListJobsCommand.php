<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * List AtoM jobs.
 *
 * Delegates to: php symfony jobs:list
 */
class ListJobsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'jobs:list';
    protected string $description = 'List AtoM jobs';
    protected string $symfonyTask = 'jobs:list';
}
