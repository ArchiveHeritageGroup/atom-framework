<?php

namespace AtomFramework\Console\Commands\Propel;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Generate slugs for all slug-less objects.
 *
 * Delegates to: php symfony propel:generate-slugs
 */
class GenerateSlugsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'propel:generate-slugs';
    protected string $description = 'Generate slugs for all slug-less objects';
    protected string $symfonyTask = 'propel:generate-slugs';
}
