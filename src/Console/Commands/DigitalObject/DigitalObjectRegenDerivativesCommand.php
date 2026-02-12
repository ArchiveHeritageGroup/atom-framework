<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Regenerate digital object derivative from master copy.
 *
 * Delegates to: php symfony digitalobject:regen-derivatives
 */
class DigitalObjectRegenDerivativesCommand extends SymfonyBridgeCommand
{
    protected string $name = 'digitalobject:regen-derivatives';
    protected string $description = 'Regenerate digital object derivative from master copy';
    protected string $symfonyTask = 'digitalobject:regen-derivatives';
}
