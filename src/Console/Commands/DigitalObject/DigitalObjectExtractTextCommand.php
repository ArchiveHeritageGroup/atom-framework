<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Extract text from PDFs for search indexing.
 *
 * Delegates to: php symfony digitalobject:extract-text
 */
class DigitalObjectExtractTextCommand extends SymfonyBridgeCommand
{
    protected string $name = 'digitalobject:extract-text';
    protected string $description = 'Extract text from PDFs for search indexing';
    protected string $symfonyTask = 'digitalobject:extract-text';
}
