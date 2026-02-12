<?php

namespace AtomFramework\Console\Commands\Taxonomy;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Normalize taxonomy terms.
 *
 * Delegates to: php symfony taxonomy:normalize
 */
class TaxonomyNormalizeCommand extends SymfonyBridgeCommand
{
    protected string $name = 'taxonomy:normalize';
    protected string $description = 'Normalize taxonomy terms';
    protected string $symfonyTask = 'taxonomy:normalize';
}
