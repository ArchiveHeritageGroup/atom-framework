<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Consolidate i18n strings from plugin-specific XLIFF directories.
 *
 * Delegates to Symfony for complex i18n table manipulation
 * and XLIFF file processing that depends on Symfony i18n subsystem.
 */
class ConsolidateCommand extends SymfonyBridgeCommand
{
    protected string $name = 'i18n:consolidate';
    protected string $description = 'Consolidate i18n strings from plugin-specific directories';
}
