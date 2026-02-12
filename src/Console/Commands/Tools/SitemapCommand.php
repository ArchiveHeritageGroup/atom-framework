<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Generate an XML sitemap.
 *
 * Delegates to Symfony for complex sitemap generation that requires
 * iterating over all public descriptions with Propel and nested set data.
 */
class SitemapCommand extends SymfonyBridgeCommand
{
    protected string $name = 'tools:sitemap';
    protected string $description = 'Generate an XML sitemap';
    protected string $symfonyTask = 'tools:sitemap';

    protected function configure(): void
    {
        $this->addOption('output', 'o', 'Output file path', 'sitemap.xml');
        $this->addOption('base-url', null, 'Base URL for the sitemap');
    }
}
