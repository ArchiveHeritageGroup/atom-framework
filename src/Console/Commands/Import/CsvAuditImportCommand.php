<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\SymfonyBridgeCommand;

/**
 * Import audit records from CSV.
 *
 * Delegates to: php symfony csv:audit-import
 */
class CsvAuditImportCommand extends SymfonyBridgeCommand
{
    protected string $name = 'import:csv-audit';
    protected string $description = 'Import audit records from CSV';
    protected string $symfonyTask = 'csv:audit-import';
}
