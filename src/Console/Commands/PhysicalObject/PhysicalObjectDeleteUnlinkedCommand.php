<?php

namespace AtomFramework\Console\Commands\PhysicalObject;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Delete physical objects that are not linked to descriptions.
 *
 * Delegates to: php symfony physicalobject:delete-unlinked
 */
class PhysicalObjectDeleteUnlinkedCommand extends SymfonyBridgeCommand
{
    protected string $name = 'physicalobject:delete-unlinked';
    protected string $description = 'Delete physical objects not linked to descriptions';
    protected string $symfonyTask = 'physicalobject:delete-unlinked';
}
