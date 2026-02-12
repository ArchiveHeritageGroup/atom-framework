<?php

namespace AtomFramework\Console\Commands\Search;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Populate the search index.
 *
 * Delegates to: php symfony search:populate
 */
class PopulateCommand extends SymfonyBridgeCommand
{
    protected string $name = 'search:populate';
    protected string $description = 'Populate search index';
}
