<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Convert custom link format to Markdown syntax in i18n table fields.
 *
 * Delegates to Symfony for complex i18n table manipulation across
 * information object, actor, note, repository, and rights i18n fields.
 */
class CustomLinkToMarkdownCommand extends SymfonyBridgeCommand
{
    protected string $name = 'i18n:custom-link-to-markdown';
    protected string $description = 'Convert custom link format to Markdown syntax in i18n fields';
}
