<?php

namespace AtomFramework\Console\Commands\PhysicalObject;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Normalize physical object data.
 *
 * Delegates to: php symfony physicalobject:normalize
 */
class PhysicalObjectNormalizeCommand extends SymfonyBridgeCommand
{
    protected string $name = 'physicalobject:normalize';
    protected string $description = 'Normalize physical object data';
    protected string $symfonyTask = 'physicalobject:normalize';
}
