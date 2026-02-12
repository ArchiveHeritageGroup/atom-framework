<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Export authority record data as CSV file(s).
 *
 * Bootstraps Symfony/Propel context and uses the AtoM csvActorExport
 * writer class to export all authority records (actors).
 */
class CsvAuthorityExportCommand extends BaseCommand
{
    protected string $name = 'csv:authority-export';
    protected string $description = 'Export authority record data as CSV file(s)';

    protected string $detailedDescription = <<<'EOF'
Export authority record data as CSV file(s).

Exports all authority records (actors) with their related data to CSV.

Examples:

    php bin/atom csv:authority-export /path/to/authorities.csv
    php bin/atom csv:authority-export /path/to/authorities.csv --items-until-update=50
EOF;

    protected function configure(): void
    {
        $this->addArgument('path', 'The destination directory for export file(s)', true);
        $this->addOption('items-until-update', null, 'Indicate progress every n items');
    }

    protected function handle(): int
    {
        $path = $this->argument('path');
        $itemsUntilUpdate = $this->option('items-until-update');

        $this->checkPathIsWritable($path);

        // Bootstrap Symfony/Propel context
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $context = \sfContext::createInstance($configuration);

        // Prepare CSV exporter
        $writer = new \csvActorExport($path);
        $writer->setOptions(['relations' => true]);

        // Export actors and related data
        $itemsExported = 0;

        foreach ($this->getActors() as $row) {
            $actor = \QubitActor::getById($row['id']);
            $context->getUser()->setCulture($row['culture']);

            $writer->exportResource($actor);

            $this->indicateProgress($itemsExported, $itemsUntilUpdate);
            ++$itemsExported;
        }

        $this->newline();
        $this->success("Export complete ({$itemsExported} authority records exported).");

        return 0;
    }

    /**
     * Fetch all actor records (excluding root) with their cultures.
     */
    private function getActors(): array
    {
        $sql = "SELECT ai.id, ai.culture FROM actor_i18n ai INNER JOIN object o ON ai.id=o.id
            WHERE o.class_name='QubitActor' AND ai.id <> ?";

        return \QubitPdo::fetchAll($sql, [\QubitActor::ROOT_ID], ['fetchMode' => \PDO::FETCH_ASSOC]);
    }

    /**
     * Verify that the given path is a writable directory.
     */
    private function checkPathIsWritable(string $path): void
    {
        if (!is_dir($path)) {
            throw new \Exception('You must specify a valid directory');
        }

        if (!is_writable($path)) {
            throw new \Exception("Can't write to this directory");
        }
    }

    /**
     * Display a progress dot at configured intervals.
     */
    private function indicateProgress(int $count, ?string $itemsUntilUpdate): void
    {
        if (null === $itemsUntilUpdate || 0 === ($count % max(1, (int) $itemsUntilUpdate))) {
            echo '.';
        }
    }
}
