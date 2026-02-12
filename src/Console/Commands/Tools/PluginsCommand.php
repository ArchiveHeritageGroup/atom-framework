<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * List installed plugins and their status.
 *
 * Delegates to Symfony for plugin discovery that requires
 * the Symfony autoloader and ProjectConfiguration context.
 */
class PluginsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:plugins';
    protected string $description = 'List installed plugins and their status';
    protected string $symfonyTask = 'tools:plugins';
}
