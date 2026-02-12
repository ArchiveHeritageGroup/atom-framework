<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Remove HTML tags from i18n table fields and convert HTML entities.
 *
 * Delegates to Symfony for complex i18n table manipulation across
 * information object, actor, note, repository, and rights i18n fields.
 */
class RemoveHtmlTagsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'i18n:remove-html-tags';
    protected string $description = 'Remove HTML tags from i18n fields and convert HTML entities';
}
