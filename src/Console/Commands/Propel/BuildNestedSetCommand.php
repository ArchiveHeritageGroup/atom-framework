<?php

namespace AtomFramework\Console\Commands\Propel;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Build all nested set values.
 *
 * Delegates to: php symfony propel:build-nested-set
 */
class BuildNestedSetCommand extends SymfonyBridgeCommand
{
    protected string $name = 'propel:build-nested-set';
    protected string $description = 'Build all nested set values';
    protected string $symfonyTask = 'propel:build-nested-set';
}
