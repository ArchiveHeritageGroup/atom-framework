<?php

namespace AtomFramework\Console\Commands\Actor;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Move actor-description relations.
 *
 * Delegates to: php symfony actor:move-description-relations
 */
class MoveDescriptionRelationsCommand extends SymfonyBridgeCommand
{
    protected string $name = 'actor:move-description-relations';
    protected string $description = 'Move actor-description relations';
    protected string $symfonyTask = 'actor:move-description-relations';
}
