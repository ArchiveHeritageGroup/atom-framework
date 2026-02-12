<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Output a diff of removed and added i18n messages.
 *
 * Delegates to Symfony for i18n extraction and comparison
 * that depends on sfI18nExtract and XLIFF processing.
 */
class DiffCommand extends SymfonyBridgeCommand
{
    protected string $name = 'i18n:diff';
    protected string $description = 'Output a list of removed and added i18n messages for auditing';
}
