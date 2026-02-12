<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Delete digital objects given an archival description slug.
 *
 * Delegates to: php symfony digitalobject:delete
 */
class DigitalObjectDeleteCommand extends SymfonyBridgeCommand
{
    protected string $name = 'digitalobject:delete';
    protected string $description = 'Delete digital objects given an archival description slug';
    protected string $symfonyTask = 'digitalobject:delete';
}
