<?php

namespace AtomFramework\Console\Commands\FindingAid;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Generate a Finding Aid document.
 *
 * Delegates to: php symfony finding-aid:generate
 */
class FindingAidGenerateCommand extends SymfonyBridgeCommand
{
    protected string $name = 'finding-aid:generate';
    protected string $description = 'Generate a Finding Aid document';
    protected string $symfonyTask = 'finding-aid:generate';
}
