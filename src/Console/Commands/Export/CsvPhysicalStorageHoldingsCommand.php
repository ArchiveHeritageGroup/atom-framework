<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Export physical storage holdings report as CSV data.
 *
 * Bootstraps Symfony/Propel context and uses the AtoM
 * QubitPhysicalObjectCsvHoldingsReport class to generate the report.
 */
class CsvPhysicalStorageHoldingsCommand extends BaseCommand
{
    protected string $name = 'csv:physicalstorage-holdings';
    protected string $description = 'Export physical storage holdings report as CSV data';

    protected string $detailedDescription = <<<'EOF'
Export physical storage holdings report as CSV data.

Physical storage containing no holdings will be included unless the
--omit-empty option is used.

Holdings can be filtered by type. Valid holding types are:
  "description" (information objects)
  "accession" (accessions)
  "none" (omit non-empty physical storage)

Examples:

    php bin/atom csv:physicalstorage-holdings /path/to/report.csv
    php bin/atom csv:physicalstorage-holdings /path/to/report.csv --omit-empty
    php bin/atom csv:physicalstorage-holdings /path/to/report.csv --holding-type=description
EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'Output filename', true);
        $this->addOption('omit-empty', 's', 'Omit physical storage without holdings');
        $this->addOption('holding-type', 'e', 'Only include specific holding type ("description", "accession", or "none")');
    }

    protected function handle(): int
    {
        // Bootstrap Symfony/Propel context
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $context = \sfContext::createInstance($configuration);
        $databaseManager = new \sfDatabaseManager($configuration);

        $filename = $this->argument('filename');

        $this->info('Exporting physical storage holdings report...');

        $reportOptions = $this->getReportOptions();
        $report = new \QubitPhysicalObjectCsvHoldingsReport($reportOptions);
        $report->write($filename);

        $this->success('Done.');

        return 0;
    }

    /**
     * Build the report options array from command options.
     */
    private function getReportOptions(): array
    {
        $this->validateHoldingType();

        $reportOptions = [];
        $reportOptions['suppressEmpty'] = $this->hasOption('omit-empty');

        $holdingType = $this->option('holding-type');
        if (!empty($holdingType)) {
            $type = strtolower($holdingType);
            $reportOptions['holdingType'] = ('none' === $type)
                ? $type
                : \QubitPhysicalObjectCsvHoldingsReport::$defaultTypeMap[$holdingType];
        }

        return $reportOptions;
    }

    /**
     * Validate the holding type option if provided.
     */
    private function validateHoldingType(): void
    {
        $holdingType = $this->option('holding-type');

        if (empty($holdingType)) {
            return;
        }

        $allowedValues = array_merge(
            array_keys(\QubitPhysicalObjectCsvHoldingsReport::$defaultTypeMap),
            ['none']
        );

        if (!in_array($holdingType, $allowedValues)) {
            $message = sprintf(
                'Invalid holding type "%s" (must be one of: %s).',
                $holdingType,
                implode(', ', $allowedValues)
            );

            throw new \Exception($message);
        }
    }
}
