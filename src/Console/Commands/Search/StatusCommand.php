<?php

namespace AtomFramework\Console\Commands\Search;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Display search index status.
 *
 * Delegates to: php symfony search:status
 */
class StatusCommand extends SymfonyBridgeCommand
{
    protected string $name = 'search:status';
    protected string $description = 'Show search index status';
}
