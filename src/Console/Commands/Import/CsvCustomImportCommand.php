<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Custom CSV import using user-defined criteria.
 *
 * Native implementation of the csv:custom-import Symfony task.
 */
class CsvCustomImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-custom';
    protected string $description = 'Custom CSV import';

    protected string $detailedDescription = <<<'EOF'
    Import CSV data using import logic defined in an external PHP file.
    The import definition file must return a QubitFlatfileImport instance.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('rows-until-update', null, 'Output total rows imported every n rows');
        $this->addOption('skip-rows', null, 'Skip n rows before importing');
        $this->addOption('error-log', null, 'File to log errors to');
        $this->addOption('source-name', null, 'Source name to use when inserting keymap entries');
        $this->addOption('index', null, 'Index for search during import');
        $this->addOption('update', null, 'Attempt to update if record already exists. Valid values: "match-and-update", "delete-and-replace"');
        $this->addOption('skip-matched', null, 'Skip creating new records when existing one matches (without --update)');
        $this->addOption('skip-unmatched', null, 'Skip creating new records if no existing records match (with --update)');
        $this->addOption('import-definition', null, 'PHP file defining and returning an import object');
        $this->addOption('output-file', null, 'Optional output file parameter which can be referenced by import definition logic');
        $this->addOption('ignore-bad-lod', null, 'Add rows with an unrecognized level of description to end of file, instead of dropping them');
    }

    protected function handle(): int
    {
        $filename = $this->argument('filename');

        $skipRows = ($this->option('skip-rows')) ? $this->option('skip-rows') : 0;

        $importDefinition = $this->option('import-definition');
        if (!$importDefinition) {
            $this->error('You must specify an import definition file with --import-definition');

            return 1;
        }

        if (!file_exists($importDefinition)) {
            $this->error(sprintf('Import definition file not found: %s', $importDefinition));

            return 1;
        }

        if (false === $fh = fopen($filename, 'rb')) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        // Get import definition
        $import = require $importDefinition;

        $import->csv($fh, $skipRows);

        $this->success('Custom CSV import complete.');

        return 0;
    }
}
