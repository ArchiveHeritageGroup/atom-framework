<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Bridges\PropelBridge;
use AtomFramework\Console\BaseCommand;

/**
 * Delete data created by an import.
 */
class DeleteImportCommand extends BaseCommand
{
    protected string $name = 'import:delete';
    protected string $description = 'Delete imported records';
    protected string $detailedDescription = <<<'EOF'
Delete data created by the named import from the AtoM database.

Uses the keymap table to find records created by a specific import source name,
then deletes the objects and their keymap entries in reverse order (LIFO) to
avoid parent_id constraint violations.

Examples:
  php bin/atom import:delete my-import-name
  php bin/atom import:delete my-import-name --force
  php bin/atom import:delete my-import-name --logfile=delete.log --verbose
EOF;

    private array $objectIds = [];
    private int $totalCount = 0;
    private float $startTime = 0;

    protected function configure(): void
    {
        $this->addArgument('name', 'The import "source" name (keymap.source_name value)');
        $this->addOption('force', 'f', 'Don\'t prompt for confirmation before deleting');
        $this->addOption('logfile', 'l', 'Log output to the named file');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $importName = $this->argument('name');

        if (empty($importName)) {
            $this->error('You must specify an import source name');

            return 1;
        }

        // Get import rows from keymap table in reverse order (LIFO)
        $results = \QubitPdo::fetchAll(
            'SELECT target_id FROM keymap WHERE source_name=:name ORDER BY id DESC',
            [':name' => $importName],
            ['fetchMode' => \PDO::FETCH_COLUMN]
        );

        if (count($results) < 1) {
            $this->error("No data for import \"{$importName}\" found in the keymap table");

            return 1;
        }

        $this->totalCount = count($results);
        $this->objectIds = $results;

        $this->info("Found {$this->totalCount} database records created by import \"{$importName}\"");

        // Confirm deletion
        if (!$this->hasOption('force')) {
            $this->newline();
            $this->warning("Continuing will delete {$this->totalCount} database records and related data.");
            $this->line('THIS DATA DELETION CAN NOT BE REVERSED!!!');
            $this->line('Creating a database backup before proceeding is HIGHLY recommended!');
            $this->newline();

            if (!$this->confirm('Are you sure you want to delete this data?')) {
                $this->info('Task aborted!');

                return 0;
            }
        }

        $this->startTime = microtime(true);
        $count = 0;

        foreach ($this->objectIds as $id) {
            $obj = \QubitObject::getById($id);

            if (null === $obj) {
                $this->warning("Could not find object id: {$id}, skipping");

                continue;
            }

            $elapsed = round(microtime(true) - $this->startTime, 2);

            if ($this->verbose) {
                $this->line("[+{$elapsed}s] Deleting \"{$obj->slug}\"");
            }

            $obj->delete();

            // Delete keymap row
            \QubitPdo::modify(
                'DELETE FROM keymap WHERE source_name=:name AND target_id=:id',
                [':name' => $importName, ':id' => $id]
            );

            if ($this->verbose) {
                $this->line("[+{$elapsed}s] Deleted keymap row for import \"{$importName}\" target_id {$id}");
            }

            ++$count;
        }

        $elapsed = round(microtime(true) - $this->startTime, 2);
        $this->success("Deleted {$count} database records created by import \"{$importName}\" in {$elapsed}s");

        return 0;
    }
}
