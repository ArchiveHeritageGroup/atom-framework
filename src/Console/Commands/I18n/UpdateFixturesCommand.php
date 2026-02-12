<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Update i18n fixtures from XLIFF translation files.
 *
 * Delegates to Symfony for XLIFF extraction and fixture
 * update that depends on Symfony i18n subsystem.
 */
class UpdateFixturesCommand extends SymfonyBridgeCommand
{
    protected string $name = 'i18n:update-fixtures';
    protected string $description = 'Update i18n fixtures from XLIFF translation files';
}
