<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import authority record relations from CSV.
 *
 * Delegates to: php symfony csv:authority-relation-import
 */
class CsvAuthorityRecordRelationImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-authority-record-relation';
    protected string $description = 'Import authority record relations';
    protected string $symfonyTask = 'csv:authority-relation-import';
}
