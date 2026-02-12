<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Export accession record data to a CSV file.
 *
 * Bootstraps Symfony/Propel context and uses the AtoM csvAccessionExport
 * writer class to export all accession records.
 */
class CsvAccessionExportCommand extends BaseCommand
{
    protected string $name = 'csv:accession-export';
    protected string $description = 'Export accession record data to a CSV file';

    protected string $detailedDescription = <<<'EOF'
Exports all accession record data to a CSV file.

For example, this command exports all accessions to a CSV file:

    php bin/atom csv:accession-export /path/to/accession.csv

The --items-until-update option controls how often a progress dot is displayed.
EOF;

    protected function configure(): void
    {
        $this->addArgument('path', 'The destination file path for the CSV export', true);
        $this->addOption('items-until-update', null, 'Indicate progress every n items');
    }

    protected function handle(): int
    {
        // Bootstrap Symfony/Propel context
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $context = \sfContext::createInstance($configuration);

        $path = $this->argument('path');
        $itemsUntilUpdate = $this->option('items-until-update');

        // Prepare CSV exporter
        $writer = new \csvAccessionExport($path);
        $writer->loadResourceSpecificConfiguration('QubitAccession');

        $itemsExported = 0;

        $rows = $this->getAccessionRecords();
        $numItems = count($rows);

        $this->info("Found {$numItems} accession records to export. Starting export.");

        foreach ($rows as $row) {
            $accessionRecord = \QubitAccession::getById($row['id']);
            $context->getUser()->setCulture($row['culture']);

            $writer->exportResource($accessionRecord);

            $this->indicateProgress($itemsExported, $itemsUntilUpdate);
            ++$itemsExported;
        }

        $this->newline();
        $this->success("Export complete! ({$itemsExported} accession records exported).");

        return 0;
    }

    /**
     * Fetch all accession record IDs and cultures.
     */
    private function getAccessionRecords(): array
    {
        $sql = 'SELECT ai.id, ai.culture '
            . 'FROM accession_i18n ai '
            . 'INNER JOIN object o ON ai.id=o.id '
            . "WHERE o.class_name='QubitAccession';";

        return \QubitPdo::fetchAll($sql, null, ['fetchMode' => \PDO::FETCH_ASSOC]);
    }

    /**
     * Display a progress dot at configured intervals.
     */
    private function indicateProgress(int $count, ?string $itemsUntilUpdate): void
    {
        if (null === $itemsUntilUpdate || 0 === ($count % (int) $itemsUntilUpdate)) {
            echo '.';
        }
    }
}
