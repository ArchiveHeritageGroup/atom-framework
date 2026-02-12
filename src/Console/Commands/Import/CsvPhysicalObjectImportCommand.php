<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Import physical objects from CSV.
 *
 * Native implementation of the csv:physicalobject-import Symfony task.
 */
class CsvPhysicalObjectImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-physical-object';
    protected string $description = 'Import physical objects from CSV';

    protected string $detailedDescription = <<<'EOF'
    Import physical object CSV data into AtoM. Supports update mode, partial
    matching, multi-match handling, and search indexing during import.
    Uses the PhysicalObjectCsvImporter service class.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('culture', null, 'ISO 639-1 code for rows without an explicit culture', 'en');
        $this->addOption('debug', 'd', 'Enable debug mode');
        $this->addOption('empty-overwrite', 'e', 'When set an empty CSV value will overwrite existing data (update only)');
        $this->addOption('error-log', 'l', 'Log errors to indicated file');
        $this->addOption('header', null, 'Provide column names (CSV format) and import first row of file as data');
        $this->addOption('index', 'i', 'Update search index during import');
        $this->addOption('multi-match', null, 'Action when matching more than one existing record: "skip", "first", "all"', 'skip');
        $this->addOption('partial-matches', 'p', 'Match existing records if first part of name matches import name');
        $this->addOption('rows-until-update', 'r', 'Show import progress every n rows (n=0: errors only)', '1');
        $this->addOption('skip-rows', 'o', 'Skip n rows before importing', '0');
        $this->addOption('skip-unmatched', 's', 'Skip unmatched records during update instead of creating new records');
        $this->addOption('source-name', null, 'Source name to use when inserting keymap entries');
        $this->addOption('update', 'u', 'Update existing record if name matches imported name');
    }

    protected function handle(): int
    {
        $filename = $this->argument('filename');

        $importOptions = $this->setImportOptions();

        $importer = new \PhysicalObjectCsvImporter(
            \sfContext::getInstance(),
            \Propel::getConnection(),
            $importOptions
        );
        $importer->setFilename($filename);

        $this->info(sprintf(
            'Importing physical object data from %s...',
            $importer->getFilename()
        ));

        $skipRows = $this->option('skip-rows');
        if ($skipRows && $skipRows > 0) {
            if (1 == $skipRows) {
                $this->line('Skipping first row...');
            } else {
                $this->line(sprintf('Skipping first %u rows...', $skipRows));
            }
        }

        $importer->doImport();

        $this->line(sprintf(
            'Done! Imported %u of %u rows.',
            $importer->countRowsImported(),
            $importer->countRowsTotal()
        ));

        $this->line($importer->reportTimes());

        $this->success('Physical object CSV import complete.');

        return 0;
    }

    private function setImportOptions(): array
    {
        $this->validateOptions();

        $opts = [];

        $keymap = [
            'culture' => 'defaultCulture',
            'debug' => 'debug',
            'empty-overwrite' => 'overwriteWithEmpty',
            'error-log' => 'errorLog',
            'header' => 'header',
            'index' => 'updateSearchIndex',
            'skip-rows' => 'offset',
            'skip-unmatched' => 'insertNew',
            'multi-match' => 'onMultiMatch',
            'partial-matches' => 'partialMatches',
            'rows-until-update' => 'progressFrequency',
            'source-name' => 'sourceName',
            'update' => 'updateExisting',
        ];

        foreach ($keymap as $oldkey => $newkey) {
            $value = $this->option($oldkey);
            if (empty($value)) {
                continue;
            }

            // Invert value of skip-unmatched
            if ('skip-unmatched' == $oldkey) {
                $opts[$newkey] = !$this->hasOption($oldkey);

                continue;
            }

            $opts[$newkey] = $value;
        }

        return $opts;
    }

    private function validateOptions(): void
    {
        if ($this->hasOption('skip-unmatched') && !$this->hasOption('update')) {
            throw new \sfException('The --skip-unmatched option can not be used without the --update option.');
        }
    }
}
