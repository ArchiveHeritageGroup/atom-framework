<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Load a CSV list of digital objects.
 *
 * Delegates to: php symfony digitalobject:load
 */
class DigitalObjectLoadCommand extends SymfonyBridgeCommand
{
    protected string $name = 'digitalobject:load';
    protected string $description = 'Load a CSV list of digital objects';
    protected string $symfonyTask = 'digitalobject:load';
}
