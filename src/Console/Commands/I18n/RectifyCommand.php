<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Rectify existing i18n strings for the application.
 *
 * Delegates to Symfony for i18n string rectification that
 * depends on sfFactoryConfigHandler and XLIFF processing.
 */
class RectifyCommand extends SymfonyBridgeCommand
{
    protected string $name = 'i18n:rectify';
    protected string $description = 'Rectify existing i18n strings for the application';
}
