<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Remove duplicate i18n source strings across plugins.
 *
 * Delegates to Symfony for plugin directory traversal and
 * XLIFF duplicate detection that depends on sfFinder.
 */
class RemoveDuplicatesCommand extends SymfonyBridgeCommand
{
    protected string $name = 'i18n:remove-duplicates';
    protected string $description = 'Remove duplicate i18n source strings across plugins';
}
