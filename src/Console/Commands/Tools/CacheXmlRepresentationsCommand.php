<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Render all descriptions as XML and cache the results as files.
 *
 * Delegates to: php symfony cache:xml-representations
 */
class CacheXmlRepresentationsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'cache:xml-representations';
    protected string $description = 'Render all descriptions as XML and cache the results as files';
    protected string $symfonyTask = 'cache:xml-representations';
}
