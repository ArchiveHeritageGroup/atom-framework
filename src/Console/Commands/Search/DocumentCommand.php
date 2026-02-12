<?php

namespace AtomFramework\Console\Commands\Search;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Print indexing configuration / document data.
 *
 * Delegates to: php symfony search:document
 */
class DocumentCommand extends SymfonyBridgeCommand
{
    protected string $name = 'search:document';
    protected string $description = 'Print indexing configuration';
}
